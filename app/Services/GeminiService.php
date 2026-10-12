<?php

namespace App\Services;

use App\Exceptions\GeminiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Total seconds a single generate call may spend across all keys and models. */
    private const TIME_BUDGET = 150;

    private array $apiKeys;

    private array $models;

    private string $thinkingLevel;

    public function __construct()
    {
        $this->apiKeys = $this->csvList([
            config('services.gemini.api_key'),
            config('services.gemini.fallback_api_key'),
            config('services.gemini.api_keys'),
        ]);
        $this->models = $this->csvList([config('services.gemini.models')]) ?: ['gemini-flash-latest'];
        $this->thinkingLevel = (string) config('services.gemini.thinking_level');
    }

    public function isConfigured(): bool
    {
        return $this->apiKeys !== [];
    }

    /**
     * Convert the uploaded CV into the template schema and tailor it for the job.
     *
     * @throws GeminiException
     */
    public function tailorUploadedCvContent(
        string $jobDescription,
        string $uploadedCvText,
        array $layoutTemplate,
        string $tone = 'professional',
        ?array $uploadedFile = null
    ): array {
        $parts = [[
            'text' => $this->buildUploadedCvPrompt(
                $jobDescription,
                $uploadedCvText,
                $this->emptySchemaFromTemplate($layoutTemplate),
                $tone,
                $uploadedFile
            ),
        ]];

        if ($uploadedFile) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $uploadedFile['mime_type'],
                    'data' => $uploadedFile['data'],
                ],
            ];
        }

        $parsed = $this->generateJson($parts, 90);
        $candidate = $parsed['cv'] ?? $parsed;

        if (! is_array($candidate)) {
            throw new GeminiException('The AI response did not include a valid CV.');
        }

        return [
            'cv' => $this->normalizeGeneratedCv($candidate, $layoutTemplate),
            'reasoning' => is_string($parsed['reasoning'] ?? null) ? $parsed['reasoning'] : '',
        ];
    }

    /**
     * Pull structured job details out of raw job page text.
     *
     * @throws GeminiException
     */
    public function extractJobPosting(string $pageText, string $url): array
    {
        $pageText = Str::limit($pageText, 15000, '');

        $prompt = <<<PROMPT
You extract job postings from web page text. Return ONLY JSON with exactly these keys:
{
  "is_job_posting": true or false,
  "title": "job title",
  "company": "hiring company",
  "location": "job location",
  "description": "the full job description: responsibilities, requirements, skills and benefits as plain text with line breaks; no navigation, cookie or footer text",
  "application_email": "email address candidates should send applications to, or empty string",
  "apply_url": "absolute URL of the application form if the page names one, or empty string"
}
Copy the description faithfully; do not summarise or invent anything. Use empty strings for anything not on the page.

PAGE URL: {$url}

PAGE TEXT:
{$pageText}
PROMPT;

        $job = $this->generateJson([['text' => $prompt]], 45, preferFastModel: true);

        return [
            'is_job_posting' => (bool) ($job['is_job_posting'] ?? false),
            'title' => $this->textValue($job['title'] ?? ''),
            'company' => $this->textValue($job['company'] ?? ''),
            'location' => $this->textValue($job['location'] ?? ''),
            'description' => $this->textValue($job['description'] ?? ''),
            'application_email' => $this->textValue($job['application_email'] ?? ''),
            'apply_url' => $this->textValue($job['apply_url'] ?? ''),
        ];
    }

    /**
     * Write a short application email (cover note) from the tailored CV.
     *
     * @throws GeminiException
     */
    public function draftApplicationEmail(array $cv, array $job, string $tone = 'professional'): array
    {
        $candidate = json_encode([
            'name' => data_get($cv, 'personal.name'),
            'email' => data_get($cv, 'personal.email'),
            'phone' => data_get($cv, 'personal.phone'),
            'about' => $cv['about'] ?? '',
            'experience' => array_map(
                fn ($entry) => array_intersect_key((array) $entry, array_flip(['title', 'company', 'period', 'summary'])),
                array_slice($cv['experience'] ?? [], 0, 4)
            ),
            'skills' => $cv['skills'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jobTitle = $job['title'] ?: 'the advertised role';
        $company = $job['company'] ?: 'the company';
        $jobDescription = Str::limit((string) ($job['description'] ?? ''), 6000, '');

        $prompt = <<<PROMPT
Write a job application email from the candidate below. Return ONLY JSON:
{"subject": "...", "body": "..."}

Rules:
- Subject: "Application for {$jobTitle} - <candidate name>" unless the job description asks for a specific subject line or reference code; then follow it exactly.
- Body: plain text, 120-180 words, {$tone} tone. Greeting ("Dear Hiring Manager," unless a contact name is given), 2 short paragraphs on why the candidate fits {$jobTitle} at {$company}, one line saying the tailored CV is attached as a PDF, then a sign-off with the candidate's name, phone and email.
- Use only facts from the candidate data. Do not invent experience, skills, numbers or availability.

CANDIDATE:
{$candidate}

JOB DESCRIPTION:
{$jobDescription}
PROMPT;

        $email = $this->generateJson([['text' => $prompt]], 45, preferFastModel: true);
        $subject = $this->textValue($email['subject'] ?? '');
        $body = is_string($email['body'] ?? null) ? trim($email['body']) : '';

        if ($subject === '' || $body === '') {
            throw new GeminiException('The AI did not return an email draft.');
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Send one prompt and decode the JSON answer, rotating keys and models on failure.
     *
     * @param  bool  $preferFastModel  try the models in reverse order (lightest first) for short tasks
     *
     * @throws GeminiException
     */
    private function generateJson(array $parts, int $timeout, bool $preferFastModel = false): array
    {
        if (! $this->isConfigured()) {
            throw new GeminiException('Gemini API key is not configured. Set GEMINI_API_KEY in .env.');
        }

        $generationConfig = ['responseMimeType' => 'application/json'];
        if ($this->thinkingLevel !== '') {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $this->thinkingLevel];
        }

        $payload = ['contents' => [['parts' => $parts]], 'generationConfig' => $generationConfig];
        $models = $preferFastModel ? array_reverse($this->models) : $this->models;
        $deadline = microtime(true) + self::TIME_BUDGET;
        $lastStatus = null;
        $lastModel = end($models);

        foreach ($models as $model) {
            foreach ($this->apiKeys as $keyIndex => $apiKey) {
                // Overload and timeouts affect the whole model, so try the next model instead of another key.
                if ($keyIndex > 0 && $model !== $lastModel && $this->isModelWideFailure($lastStatus)) {
                    break;
                }

                $remaining = (int) floor($deadline - microtime(true));
                if ($remaining < 10) {
                    break 2;
                }

                try {
                    $response = Http::timeout(min($timeout, $remaining))
                        ->connectTimeout(10)
                        ->withHeaders(['X-goog-api-key' => $apiKey])
                        ->post(self::API_BASE.$model.':generateContent', $payload);
                } catch (ConnectionException $e) {
                    $lastStatus = 'timeout';
                    Log::warning('Gemini request failed to connect', ['model' => $model, 'key' => $keyIndex + 1, 'error' => $e->getMessage()]);

                    continue;
                }

                if (! $response->successful()) {
                    $lastStatus = $response->status();
                    Log::warning('Gemini request rejected', ['model' => $model, 'key' => $keyIndex + 1, 'status' => $lastStatus]);

                    continue;
                }

                $text = $response->json('candidates.0.content.parts.0.text');
                $decoded = is_string($text) ? json_decode($this->extractJson($text), true) : null;

                if (is_array($decoded)) {
                    return $decoded;
                }

                $lastStatus = 'invalid-json';
                Log::warning('Gemini returned unusable output', [
                    'model' => $model,
                    'finish_reason' => $response->json('candidates.0.finishReason'),
                ]);
            }
        }

        throw new GeminiException($this->failureMessage($lastStatus));
    }

    private function isModelWideFailure(int|string|null $status): bool
    {
        return $status === 'timeout' || (is_int($status) && $status >= 500);
    }

    private function failureMessage(int|string|null $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'All Gemini API keys were rejected. Check GEMINI_API_KEY in .env.',
            $status === 429 => 'Gemini rate limit reached on every configured key. Wait a minute and try again.',
            $status === 'timeout' => 'Gemini did not respond in time. Check your internet connection and try again.',
            $status === 'invalid-json' => 'The AI returned an unreadable answer. Please try again.',
            default => 'Gemini is temporarily unavailable. Please try again in a moment.',
        };
    }

    private function csvList(array $values): array
    {
        $items = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $item) {
                $item = trim($item, " \t\"'");
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function extractJson(string $response): string
    {
        $clean = trim($response);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($clean, $start, $end - $start + 1);
        }

        return $clean;
    }

    private function buildUploadedCvPrompt(
        string $jobDescription,
        string $uploadedCvText,
        array $emptySchema,
        string $tone,
        ?array $uploadedFile = null
    ): string {
        $schemaJson = json_encode($emptySchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fileNote = $uploadedFile
            ? "The original uploaded CV file is attached as {$uploadedFile['mime_type']} named {$uploadedFile['name']}. Use the attached file as the primary CV source. The extracted text below is helper text and may be incomplete."
            : 'No original file attachment is available to the model. Use the extracted uploaded CV text below as the CV source.';
        $uploadedCvText = trim($uploadedCvText);
        if ($uploadedFile && $uploadedCvText !== '') {
            $uploadedCvText = mb_substr($uploadedCvText, 0, 4000);
        } elseif ($uploadedCvText !== '') {
            $uploadedCvText = mb_substr($uploadedCvText, 0, 12000);
        } else {
            $uploadedCvText = '[Text extraction was empty. Read the attached uploaded CV file directly.]';
        }
        $toneInstructions = $this->toneInstructions($tone);

        return <<<PROMPT
You are converting a user's uploaded CV into a structured CV JSON object and tailoring it for a job.

Return ONLY valid JSON. Do not include markdown fences, comments, or explanations outside JSON.

Your response shape must be exactly:
{
  "cv": { ...full CV JSON using the provided schema... },
  "reasoning": "Brief summary of how the uploaded CV was tailored"
}

Critical rules:
- The uploaded CV content is the ONLY factual source for the candidate.
- {$fileNote}
- The provided JSON is an empty layout schema only. Do not copy sample/default names, dates, companies, schools, emails, skills, or experience from anywhere else.
- Do not invent employers, dates, degrees, certifications, tools, achievements, metrics, portfolio links, or seniority.
- If a field is missing from the uploaded CV, leave it blank or use an empty array.
- Do not create placeholder objects for missing sections. For example, if no other languages are present, use "other": []; if no certifications are present, use "certifications": [].
- If the uploaded CV includes languages, certifications, projects, driving licence, hobbies, or interests, include those sections in the output even when they are not relevant to the job.
- Only include a section item when it has real content from the uploaded CV.
- Keep the template schema: personal object, about string, education array, experience array, projects array, languages object, driving string, interests array, hobbies string, skills array, certifications array.
- Use these exact keys where possible:
  - experience: title, company, period, location, website, summary, description, business_sector, department, projects, highlights.
  - projects: period, title, subtitle, description, link.
  - skills: label, items.
  - certifications: type, title, detail.
  - languages.other: name, listening, reading, speaking_production, speaking_interaction, writing.
- Skills must be grouped like the template, not one skill per object. Use broad labels such as "Professional", "Technical", "Tools", and "Soft skills"; each group must contain an items array with multiple skills where possible.
- Optimize the CV for the job description as strongly as possible while staying truthful.
- Cover as many of the job's required keywords and responsibilities as the CV genuinely supports.
- Identify the job's required skills, tools, responsibilities, domain keywords, and soft skills.
- If a required job skill or tool is present anywhere in the uploaded CV, include it in the skills section and relevant experience/project wording.
- Reorder skills so the job's required skills appear first within the most relevant groups.
- Rewrite work experience summaries, descriptions, projects, and highlights to emphasize responsibilities and outcomes that match the job description.
- Write interests as one polished role-matching paragraph, not multiple points. It should support the job target and mention relevant professional interests naturally.
- Do not add missing skills, tools, achievements, or experience unless they are supported by the uploaded CV text or attached CV file.
- Prefer direct, recruiter-friendly English.

WRITING STYLE PROFILE:
{$toneInstructions}

JOB DESCRIPTION:
{$jobDescription}

UPLOADED CV TEXT:
{$uploadedCvText}

OUTPUT JSON SCHEMA TO FILL:
{$schemaJson}
PROMPT;
    }

    private function toneInstructions(string $tone): string
    {
        return match (strtolower(trim($tone))) {
            'concise' => <<<TEXT
Style: Concise.
- Keep the about section short, around 2-3 focused sentences.
- Use compact experience descriptions and short bullet-style highlights.
- Remove repetition and low-relevance wording.
- Prioritize only the strongest job-matching skills and responsibilities.
- Keep every section tight while preserving factual CV content.
TEXT,
            'detailed' => <<<TEXT
Style: Detailed.
- Use a richer about section with clear job alignment.
- Expand relevant work experience, projects, and highlights with more context.
- Include more supported job keywords where they naturally fit.
- Preserve optional sections such as certifications, languages, interests, and hobbies when present.
- Make the CV feel complete and specific without inventing facts.
TEXT,
            default => <<<TEXT
Style: Professional.
- Use balanced, polished recruiter-friendly language.
- Keep sections clear, confident, and specific.
- Emphasize job-matching skills and experience without over-writing.
- Use natural keywords from the job description where supported by the CV.
- Preserve readability and truthfulness.
TEXT,
        };
    }


    private function emptySchemaFromTemplate(array $template): array
    {
        return [
            'meta' => [
                'template_name' => data_get($template, 'meta.template_name', 'Uploaded CV'),
                'target_role' => '',
                'version' => '1.0',
            ],
            'personal' => [
                'name' => '',
                'date_of_birth' => '',
                'place_of_birth' => '',
                'nationality' => '',
                'gender' => '',
                'phone' => '',
                'email' => '',
                'whatsapp' => '',
                'address' => '',
            ],
            'about' => '',
            'education' => [],
            'experience' => [],
            'projects' => [],
            'languages' => [
                'mother' => '',
                'other' => [],
            ],
            'driving' => '',
            'interests' => [],
            'hobbies' => '',
            'skills' => [],
            'certifications' => [],
        ];
    }

    private function normalizeGeneratedCv(array $candidate, array $layoutTemplate): array
    {
        $schema = $this->emptySchemaFromTemplate($layoutTemplate);
        $normalized = $schema;

        foreach (array_keys($schema) as $key) {
            if (array_key_exists($key, $candidate)) {
                $normalized[$key] = $candidate[$key];
            }
        }

        $normalized['meta'] = array_merge($schema['meta'], is_array($candidate['meta'] ?? null) ? $candidate['meta'] : []);
        $normalized['personal'] = array_merge($schema['personal'], is_array($candidate['personal'] ?? null) ? $candidate['personal'] : []);
        $normalized['languages'] = array_merge($schema['languages'], is_array($candidate['languages'] ?? null) ? $candidate['languages'] : []);

        foreach (['education', 'experience', 'projects', 'interests', 'skills', 'certifications'] as $arrayField) {
            $normalized[$arrayField] = $this->listValue($normalized[$arrayField] ?? []);
        }

        foreach (['about', 'driving', 'hobbies'] as $textField) {
            if (! is_string($normalized[$textField])) {
                $normalized[$textField] = '';
            }
        }

        $normalized['education'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeEducationEntry($entry), $normalized['education'])));
        $normalized['experience'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeExperienceEntry($entry), $normalized['experience'])));
        $normalized['projects'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeProjectEntry($entry), $normalized['projects'])));
        $normalized['interests'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeInterestEntry($entry), $normalized['interests'])));
        $normalized['skills'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeSkillEntry($entry), $normalized['skills'])));
        $normalized['certifications'] = array_values(array_filter(array_map(fn ($entry) => $this->normalizeCertificationEntry($entry), $normalized['certifications'])));
        $normalized['languages'] = $this->normalizeLanguages($normalized['languages']);

        return $normalized;
    }

    private function normalizeEducationEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $normalized = $entry;
        $normalized['date'] = $this->firstText($entry, ['date', 'period', 'year', 'years']) ?: $this->dateRange($entry);
        $normalized['degree'] = $this->firstText($entry, ['degree', 'qualification', 'title', 'name']);
        $normalized['institution'] = $this->firstText($entry, ['institution', 'school', 'university', 'college']);
        $normalized['details'] = $this->firstText($entry, ['details', 'description', 'field', 'field_of_study']);

        return $this->hasAnyText($normalized, ['date', 'location', 'degree', 'institution', 'details']) ? $normalized : null;
    }

    private function normalizeExperienceEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $normalized = $entry;
        $normalized['title'] = $this->firstText($entry, ['title', 'role', 'position', 'job_title']);
        $normalized['company'] = $this->firstText($entry, ['company', 'employer', 'organization', 'organisation']);
        $normalized['period'] = $this->firstText($entry, ['period', 'date', 'dates', 'duration']) ?: $this->dateRange($entry);
        $normalized['description'] = $this->firstText($entry, ['description', 'responsibilities', 'details']);
        $normalized['summary'] = $this->firstText($entry, ['summary', 'profile']);

        return $this->hasAnyText($normalized, ['title', 'company', 'period', 'location', 'website', 'summary', 'description', 'business_sector', 'department'])
            || ! empty($normalized['highlights'])
            ? $normalized
            : null;
    }

    private function normalizeProjectEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $normalized = $entry;
        $normalized['title'] = $this->firstText($entry, ['title', 'name', 'project']);
        $normalized['period'] = $this->firstText($entry, ['period', 'date', 'year']) ?: $this->dateRange($entry);
        $normalized['subtitle'] = $this->firstText($entry, ['subtitle', 'type', 'technologies']);
        $normalized['description'] = $this->firstText($entry, ['description', 'details', 'summary']);
        $normalized['link'] = $this->firstText($entry, ['link', 'url', 'website']);

        return $this->hasAnyText($normalized, ['period', 'title', 'subtitle', 'description', 'link']) ? $normalized : null;
    }

    private function normalizeInterestEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            return trim($entry) !== '' ? ['title' => '', 'description' => trim($entry)] : null;
        }

        if (! is_array($entry)) {
            return null;
        }

        $normalized = [
            'title' => $this->firstText($entry, ['title', 'label', 'name']),
            'description' => $this->firstText($entry, ['description', 'detail', 'details', 'value']),
        ];

        return $this->hasAnyText($normalized, ['title', 'description']) ? $normalized : null;
    }

    private function normalizeSkillEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            return trim($entry) !== '' ? ['label' => 'Skills', 'items' => [$entry]] : null;
        }

        if (! is_array($entry)) {
            return null;
        }

        $items = $entry['items'] ?? $entry['skills'] ?? $entry['value'] ?? [];
        if (is_string($items)) {
            $items = preg_split('/\s*(?:,|;|\||·|•)\s*/u', $items) ?: [];
        }

        $normalized = [
            'label' => $this->firstText($entry, ['label', 'group', 'category', 'type']) ?: 'Skills',
            'items' => array_values(array_filter(array_map(fn ($item) => $this->textValue($item), is_array($items) ? $items : []))),
        ];

        return ! empty($normalized['items']) ? $normalized : null;
    }

    private function normalizeCertificationEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            return trim($entry) !== '' ? ['type' => '', 'title' => trim($entry), 'detail' => ''] : null;
        }

        if (! is_array($entry)) {
            return null;
        }

        $normalized = [
            'type' => $this->firstText($entry, ['type', 'issuer', 'organization', 'organisation', 'provider']),
            'title' => $this->firstText($entry, ['title', 'name', 'certificate', 'certification', 'course']),
            'detail' => $this->firstText($entry, ['detail', 'details', 'description', 'date', 'mode']),
        ];

        return $this->hasAnyText($normalized, ['type', 'title', 'detail']) ? $normalized : null;
    }

    private function normalizeLanguages(mixed $value): array
    {
        $languages = is_array($value) ? $value : [];
        $other = $languages['other'] ?? $languages['others'] ?? $languages['additional'] ?? [];

        if (is_string($other)) {
            $other = preg_split('/\s*(?:,|;|\||·|•)\s*/u', $other) ?: [];
        }

        if (is_array($other)) {
            $other = array_values(array_filter(array_map(function ($entry) {
                if (is_string($entry)) {
                    return trim($entry) !== '' ? ['name' => trim($entry)] : null;
                }

                if (! is_array($entry)) {
                    return null;
                }

                $normalized = [
                    'name' => $this->firstText($entry, ['name', 'language']),
                    'listening' => $this->firstText($entry, ['listening', 'understanding_listening', 'level']),
                    'reading' => $this->firstText($entry, ['reading', 'understanding_reading', 'level']),
                    'speaking_production' => $this->firstText($entry, ['speaking_production', 'spoken_production', 'speaking', 'level']),
                    'speaking_interaction' => $this->firstText($entry, ['speaking_interaction', 'spoken_interaction', 'interaction', 'level']),
                    'writing' => $this->firstText($entry, ['writing', 'written', 'level']),
                ];

                return $this->hasAnyText($normalized, ['name', 'listening', 'reading', 'speaking_production', 'speaking_interaction', 'writing'])
                    ? $normalized
                    : null;
            }, $other)));
        } else {
            $other = [];
        }

        return [
            'mother' => $this->firstText($languages, ['mother', 'mother_tongue', 'native', 'native_language']),
            'other' => $other,
        ];
    }

    private function firstText(array $entry, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $entry)) {
                $value = $this->textValue($entry[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private function listValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            return [$value];
        }

        if (array_is_list($value)) {
            return $value;
        }

        return [$value];
    }

    private function textValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return trim(implode(', ', array_filter(array_map(fn ($item) => $this->textValue($item), $value))));
        }

        if (is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function dateRange(array $entry): string
    {
        $start = $this->firstText($entry, ['start_date', 'start', 'from']);
        $end = $this->firstText($entry, ['end_date', 'end', 'to']);

        return trim(implode(' - ', array_filter([$start, $end])));
    }

    private function hasAnyText(array $entry, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($this->textValue($entry[$field] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

}
