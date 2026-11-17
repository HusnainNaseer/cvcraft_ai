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

    /** Below this much source text the CV cannot be fact-checked reliably. */
    private const MIN_SOURCE_FOR_CHECKS = 150;

    public function __construct(private readonly CvFactGuard $guard = new CvFactGuard())
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
     * Convert the uploaded CV into the template schema, tailor it for the job, and fact-check the result
     * against the original CV: first with deterministic checks (CvFactGuard), then with an AI review of the
     * free-text sentences.
     *
     * @return array{cv: array, reasoning: string, source: string, factCheck: array{verified: bool, removed: string[]}}
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
        // Our own PDF text extraction can be incomplete, so ask the model for a transcription to check against too.
        $wantsTranscript = ($uploadedFile['mime_type'] ?? '') === 'application/pdf';

        $parts = [[
            'text' => $this->buildUploadedCvPrompt(
                $jobDescription,
                $uploadedCvText,
                $this->emptySchemaFromTemplate($layoutTemplate),
                $tone,
                $uploadedFile,
                $wantsTranscript
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

        $transcript = $wantsTranscript && is_string($parsed['source_text'] ?? null) ? $parsed['source_text'] : '';
        $source = trim($uploadedCvText."\n".$transcript);
        $cv = $this->normalizeGeneratedCv($candidate, $layoutTemplate);

        return [
            'reasoning' => is_string($parsed['reasoning'] ?? null) ? $parsed['reasoning'] : '',
            'source' => $source,
            ...$this->factCheck($cv, $source, $jobDescription),
        ];
    }

    /**
     * @return array{cv: array, factCheck: array{verified: bool, removed: string[]}}
     */
    private function factCheck(array $cv, string $source, string $jobDescription): array
    {
        if (mb_strlen($source) < self::MIN_SOURCE_FOR_CHECKS) {
            Log::warning('CV fact check skipped: not enough source text');

            return ['cv' => $cv, 'factCheck' => ['verified' => false, 'removed' => []]];
        }

        $guarded = $this->guard->enforce($cv, $source);
        $cv = $guarded['cv'];
        $removed = $guarded['removed'];

        try {
            $review = $this->verifyClaims($this->guard->claims($cv), $source, $jobDescription);
            $checked = $this->guard->applyVerdicts($cv, $review['verdicts'], $source);
            $cv = $checked['cv'];
            $removed = [...$removed, ...$checked['removed']];
            $verified = $review['complete'];
        } catch (GeminiException $e) {
            Log::warning('CV fact check by AI failed; deterministic checks only', ['error' => $e->getMessage()]);
            $verified = false;
        }

        if ($removed !== []) {
            Log::info('CV fact check corrected the tailored CV', ['changes' => count($removed)]);
        }

        return ['cv' => $cv, 'factCheck' => ['verified' => $verified, 'removed' => array_values(array_unique($removed))]];
    }

    /**
     * Ask the model to check rewritten CV text against the original CV.
     *
     * @param  array<string, string>  $claims  text keyed by an id (a CV path)
     * @return array{verdicts: array<string, array{verdict: string, text: string}>, complete: bool}
     *
     * @throws GeminiException
     */
    public function verifyClaims(array $claims, string $source, string $jobDescription = ''): array
    {
        if ($claims === []) {
            return ['verdicts' => [], 'complete' => true];
        }

        // Short ids keep the model from mangling paths; map them back afterwards.
        $ids = [];
        $numbered = [];
        foreach (array_keys($claims) as $i => $path) {
            $ids['c'.($i + 1)] = $path;
            $numbered['c'.($i + 1)] = $claims[$path];
        }

        $claimsJson = json_encode($numbered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $source = Str::limit($source, 16000, '');
        $jobDescription = Str::limit($jobDescription, 6000, '');

        $prompt = <<<PROMPT
You are a strict fact-checker. An AI rewrote parts of a candidate's CV to target a job. The ORIGINAL CV is the only source of truth about the candidate. Check every CLAIM against it.

Verdicts:
- "keep": every fact in the claim is stated in, or directly follows from, the ORIGINAL CV. Rewording, reordering, shortening, summarising and synonyms are fine.
- "fix": the claim is mostly supported but adds something the ORIGINAL CV does not state, for example a technology, responsibility, seniority or leadership ("led", "senior", "managed", "mentored", "architected"), scale ("scalable", "high-traffic", "enterprise"), domain or product type ("SaaS", "fintech"), client type, team size, number, metric or outcome. Put the corrected claim in "text": remove only the unsupported parts, keep the remaining wording, and add nothing new.
- "remove": the main point of the claim is not supported by the ORIGINAL CV.

Be strict. Wording that comes from the JOB DESCRIPTION but is not backed by the ORIGINAL CV is unsupported. A job title or degree name is supported only if the ORIGINAL CV gives it (small wording differences are fine).

Return ONLY JSON:
{"results": [{"id": "c1", "verdict": "keep", "text": "", "reason": "short reason"}]}
Include every claim id exactly once. "text" is required only for "fix".

ORIGINAL CV:
{$source}

JOB DESCRIPTION (only to spot copied wording; it is NOT evidence about the candidate):
{$jobDescription}

CLAIMS:
{$claimsJson}
PROMPT;

        // A healthy check takes 10-20s; give up sooner on a hung model so the fallback model gets its turn.
        $response = $this->generateJson([['text' => $prompt]], 35);
        $results = $response['results'] ?? null;
        if (! is_array($results)) {
            throw new GeminiException('The fact checker returned no results.');
        }

        $verdicts = [];
        foreach ($results as $result) {
            $id = is_array($result) ? (string) ($result['id'] ?? '') : '';
            $verdict = is_array($result) ? strtolower(trim((string) ($result['verdict'] ?? ''))) : '';
            if (! isset($ids[$id]) || ! in_array($verdict, ['keep', 'fix', 'remove'], true)) {
                continue;
            }

            $text = trim((string) ($result['text'] ?? ''));
            // A "fix" without corrected text cannot be applied safely, so treat it as unsupported.
            $verdicts[$ids[$id]] = ['verdict' => $verdict === 'fix' && $text === '' ? 'remove' : $verdict, 'text' => $text];
        }

        return ['verdicts' => $verdicts, 'complete' => count($verdicts) === count($ids)];
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
     * Write a short application email (cover note) from the candidate's ORIGINAL CV, then fact-check every
     * paragraph about the candidate. Throws when the draft cannot be verified, so callers fall back to a
     * plain template rather than send unchecked claims.
     *
     * @param  string  $source  text of the original uploaded CV ('' for old results; the tailored CV is used then)
     *
     * @throws GeminiException
     */
    public function draftApplicationEmail(array $cv, array $job, string $tone = 'professional', string $source = ''): array
    {
        $facts = $source !== '' ? $source : $this->cvAsText($cv);
        $contact = json_encode([
            'name' => data_get($cv, 'personal.name'),
            'email' => data_get($cv, 'personal.email'),
            'phone' => data_get($cv, 'personal.phone'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jobTitle = $job['title'] ?: 'the advertised role';
        $company = $job['company'] ?: 'the company';
        $jobDescription = Str::limit((string) ($job['description'] ?? ''), 6000, '');
        $candidateCv = Str::limit($facts, 12000, '');

        $prompt = <<<PROMPT
Write a job application email from the candidate below. Return ONLY JSON:
{"subject": "...", "body": "..."}

Rules:
- Subject: "Application for {$jobTitle} - <candidate name>" unless the job description asks for a specific subject line or reference code; then follow it exactly.
- Body: plain text, 120-180 words, {$tone} tone. Greeting ("Dear Hiring Manager," unless a contact name is given), 2 short paragraphs on why the candidate fits {$jobTitle} at {$company}, one line saying the tailored CV is attached as a PDF, then a sign-off with the candidate's name, phone and email.
- TRUTH RULE: every statement about the candidate must be stated in the CANDIDATE CV. The job description says what the employer wants; it is NOT evidence about the candidate. Do not describe the candidate with the job's wording (e.g. "SaaS", "scalable", "led") unless the CV says the same. Do not invent experience, seniority, skills, numbers, outcomes or availability. If the CV lacks a required skill, do not mention that skill.

CANDIDATE CONTACT:
{$contact}

CANDIDATE CV:
{$candidateCv}

JOB DESCRIPTION:
{$jobDescription}
PROMPT;

        $email = $this->generateJson([['text' => $prompt]], 45, preferFastModel: true);
        $subject = $this->textValue($email['subject'] ?? '');
        $body = is_string($email['body'] ?? null) ? trim($email['body']) : '';

        if ($subject === '' || $body === '') {
            throw new GeminiException('The AI did not return an email draft.');
        }

        return ['subject' => $subject, 'body' => $this->checkedEmailBody($body, $facts, $jobDescription)];
    }

    /**
     * Fact-check the paragraphs of an email body that describe the candidate.
     *
     * @throws GeminiException when the body cannot be fully verified or nothing truthful is left
     */
    private function checkedEmailBody(string $body, string $facts, string $jobDescription): string
    {
        $paragraphs = preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $body)) ?: [$body];
        $claims = [];
        foreach ($paragraphs as $i => $paragraph) {
            $paragraph = trim($paragraph);
            $isGreetingOrSignOff = preg_match('/^(dear|hi|hello|to whom|kind regards|best regards|warm regards|regards|sincerely|yours|thank you|many thanks)\b/i', $paragraph) === 1;
            if (mb_strlen($paragraph) >= 60 && ! $isGreetingOrSignOff) {
                $claims["p$i"] = $paragraph;
            }
        }

        $review = $this->verifyClaims($claims, $facts, $jobDescription);
        if (! $review['complete']) {
            throw new GeminiException('The application email could not be fully fact-checked.');
        }

        $kept = 0;
        foreach ($paragraphs as $i => $paragraph) {
            $verdict = $review['verdicts']["p$i"] ?? null;
            if ($verdict === null) {
                continue;
            }

            $text = match ($verdict['verdict']) {
                'remove' => '',
                'fix' => $verdict['text'],
                default => trim($paragraph),
            };
            $paragraphs[$i] = $this->guard->proseWithKnownNumbers($text, $facts);
            $kept += $paragraphs[$i] !== '' ? 1 : 0;
        }

        if ($claims !== [] && $kept === 0) {
            throw new GeminiException('No verifiable content was left in the application email.');
        }

        return implode("\n\n", array_filter(array_map('trim', $paragraphs), fn ($paragraph) => $paragraph !== ''));
    }

    private function cvAsText(array $cv): string
    {
        $parts = [];
        array_walk_recursive($cv, function ($value, $key) use (&$parts) {
            if (is_scalar($value) && $key !== 'photo') {
                $parts[] = (string) $value;
            }
        });

        return implode("\n", $parts);
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
        ?array $uploadedFile = null,
        bool $wantsTranscript = false
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

        $transcriptRule = $wantsTranscript
            ? "\n- Also return \"source_text\": a faithful plain-text transcription of the ORIGINAL uploaded CV file (not the tailored version). Copy it word for word; do not summarise, reorder or correct it."
            : '';
        $transcriptShape = $wantsTranscript ? ",\n  \"source_text\": \"verbatim text of the original CV\"" : '';

        return <<<PROMPT
You are tailoring a candidate's CV for a job. Tailoring means SELECTING, ORDERING, REWORDING and EMPHASISING what the CV already says so the most relevant facts stand out. It never means adding anything.

Return ONLY valid JSON, no markdown fences or comments, in exactly this shape:
{
  "cv": { ...CV JSON using the provided schema... },
  "reasoning": "Brief summary of what was emphasised or reordered and why"{$transcriptShape}
}

THE TRUTH RULE (overrides every other instruction):
- The uploaded CV is the ONLY source of facts about the candidate. {$fileNote}
- The job description is a list of what the employer wants. It is NOT evidence about the candidate. Never copy its phrases into the candidate's experience, summary or skills unless the CV states the same fact. Example: if the job says "build our SaaS platform" and the CV says "maintained WordPress sites", do NOT write "SaaS".
- Never add or upgrade: employers, job titles, seniority or leadership ("led", "senior", "managed", "mentored", "architected", "scaled"), responsibilities, technologies, skills, tools, domains or industries, clients, team sizes, numbers, percentages, metrics, outcomes, dates, degrees, grades, certifications, links, nationality, date of birth, or language levels.
- Every field you fill must be stated in the CV. If the CV does not state something, leave that field as an empty string or empty array. An empty field is always better than a guessed one. This applies especially to business_sector, department, location, website, link, date_of_birth, nationality and language levels.
- Only set languages.mother when the CV explicitly says which language is the mother tongue or native language. Otherwise list every language under "other" with empty levels unless the CV gives levels.
- Keep each job title exactly as the CV gives it.
- If a skill the job asks for is not in the CV, leave it out. Missing skills are shown to the user separately; your job is not to hide gaps.{$transcriptRule}

HOW TO TAILOR (within the truth rule):
- Put the experience, projects and skills most relevant to the job first, and use the job's terminology only where it names the same thing the CV does (e.g. CV "REST APIs" may be written as "REST API development").
- Rewrite summaries and highlights so the relevant facts stand out; you may merge, shorten or split bullet points, but each must still be backed by the CV.
- The "about" section must summarise only what the CV shows.
- Group skills like the template ("Technical", "Tools", "Professional", "Soft skills"), each group an items array.
- Include every section the CV has (languages, certifications, projects, driving licence, hobbies, interests), even when not relevant to the job. Do not create items for sections the CV does not have.
- Write interests as one short paragraph, only from interests the CV mentions.
- Do not copy anything from the empty schema's sample values.

SCHEMA KEYS:
- Top level: personal object, about string, education array, experience array, projects array, languages object, driving string, interests array, hobbies string, skills array, certifications array.
- experience: title, company, period, location, website, summary, description, business_sector, department, projects, highlights.
- projects: period, title, subtitle, description, link.
- skills: label, items.
- certifications: type, title, detail.
- languages.other: name, listening, reading, speaking_production, speaking_interaction, writing.

WRITING STYLE PROFILE:
{$toneInstructions}

JOB DESCRIPTION (what the employer wants, NOT facts about the candidate):
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
- Use the job's terminology where the CV describes the same thing.
- Preserve optional sections such as certifications, languages, interests, and hobbies when present.
- Be specific using only details the CV gives; never fill gaps.
TEXT,
            default => <<<TEXT
Style: Professional.
- Use balanced, polished recruiter-friendly language.
- Keep sections clear, confident, and specific.
- Emphasize job-matching skills and experience without over-writing.
- Use the job's terminology only where the CV describes the same thing.
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
