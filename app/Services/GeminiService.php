<?php

namespace App\Services;

use App\Exceptions\GeminiException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** Total seconds a single generate call may spend across all keys and models. */
    private const TIME_BUDGET = 150;

    /** How long a model that was overloaded, hung or retired is tried last, so later requests skip it. */
    private const COOLDOWN_SECONDS = 120;

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
     * @return array{cv: array, reasoning: string, source: string, removed: string[], fit: array}
     *
     * @throws GeminiException
     */
    public function tailorUploadedCvContent(
        string $jobDescription,
        string $uploadedCvText,
        array $layoutTemplate,
        string $tone = 'professional',
        ?array $uploadedFile = null,
        array $confirmed = [],
        bool $fillGaps = false
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
                $wantsTranscript,
                $confirmed,
                $fillGaps
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

        try {
            $parsed = $this->generateJson($parts, 60);
        } catch (GeminiException $e) {
            // The model could not read the attached file (e.g. a damaged PDF). Our own text extraction may still
            // be good enough, so try once more from the text alone.
            if ($e->getCode() !== 400 || ! $uploadedFile || mb_strlen(trim($uploadedCvText)) < self::MIN_SOURCE_FOR_CHECKS) {
                throw $e;
            }

            Log::warning('Gemini rejected the CV file; retrying with extracted text only');
            $wantsTranscript = false;
            $parsed = $this->generateJson([[
                'text' => $this->buildUploadedCvPrompt(
                    $jobDescription,
                    $uploadedCvText,
                    $this->emptySchemaFromTemplate($layoutTemplate),
                    $tone,
                    null,
                    false,
                    $confirmed,
                    $fillGaps
                ),
            ]], 60);
        }
        $candidate = $parsed['cv'] ?? $parsed;

        if (! is_array($candidate)) {
            throw new GeminiException('The AI response did not include a valid CV.');
        }

        $transcript = $wantsTranscript && is_string($parsed['source_text'] ?? null) ? $parsed['source_text'] : '';
        // Lines added to match the job (fill-gaps mode). They are shown to the candidate to check, and stored
        // with the source so the fact checks keep them.
        $added = $fillGaps ? array_slice(array_values(array_unique(array_filter(array_map(
            fn ($item) => Str::limit($this->textValue($item), 300, ''),
            is_array($parsed['added'] ?? null) ? $parsed['added'] : []
        )))), 0, 30) : [];
        $cv = $this->normalizeGeneratedCv($candidate, $layoutTemplate);

        // Every requirement the candidate confirmed, or that fill-gaps added, must show up under every heading.
        $fit = $this->normalizeFit($parsed['fit'] ?? null);
        $cv = $this->ensureCoverage($cv, $confirmed, $jobDescription, $fillGaps || $fit['level'] === 'weak', $added, $fit['target_role'], $fillGaps);
        $cv = $this->dropLowerQualifications($cv);

        if ($fillGaps) {
            // Do not depend on the AI listing its additions: every skill, project, certificate, language and
            // interest the original CV does not support is listed for the candidate, and kept.
            $base = trim(self::confirmedBlock($confirmed)."\n".$transcript."\n".$uploadedCvText);
            $added = [...$added, ...$this->unsupportedItems($cv, $base)];
            $added = array_values(array_unique($added));
        }

        // Transcript first: the fact checks read a limited amount of text, and it is the cleaner copy. What the
        // candidate confirmed and what was added for the job (including repair rounds) go first of all, so they
        // are never cut off and the fact checks keep them.
        $source = trim(self::confirmedBlock($confirmed)."\n".self::addedBlock($added)."\n".$transcript."\n".$uploadedCvText);

        // Keep the "added" list to real additions: not section labels, the headline or the whole summary.
        $noise = array_map('mb_strtolower', array_filter([
            ...array_column(is_array($cv['skills'] ?? null) ? $cv['skills'] : [], 'label'),
            (string) ($cv['personal']['headline'] ?? ''),
            (string) ($cv['about'] ?? ''),
        ]));
        $added = array_values(array_filter($added, fn (string $item) => ! in_array(mb_strtolower($item), $noise, true)
            && ! str_starts_with(mb_strtolower((string) ($cv['about'] ?? '')), mb_strtolower(mb_substr($item, 0, 60)))));

        // Fast, deterministic checks now; the slower AI review of sentences is a separate step (factCheckCv),
        // so the user sees real progress between the two.
        $removed = [];
        if (mb_strlen($source) >= self::MIN_SOURCE_FOR_CHECKS) {
            ['cv' => $cv, 'removed' => $removed] = $this->guard->enforce($cv, $source);
        }

        return [
            'cv' => $cv,
            'reasoning' => is_string($parsed['reasoning'] ?? null) ? $parsed['reasoning'] : '',
            'source' => $source,
            'removed' => $removed,
            'fit' => $fit,
            'added' => $added,
        ];
    }

    /**
     * @param  string[]  $added
     */
    public static function addedBlock(array $added): string
    {
        return $added === [] ? '' : "Added for this application to match the job (for the candidate to check):\n- ".implode("\n- ", $added);
    }

    /**
     * Make sure the tailored CV actually adopts the job's requirements in every heading. The skills section is
     * completed in code; the headline, summary and experience are sent back to the AI with exactly what is
     * missing, and checked again, up to two rounds.
     *
     * @param  string[]  $items  requirements to cover (confirmed by the candidate or added from the job)
     * @param  string[]  $added  receives lines the repair rounds add
     */
    private function ensureCoverage(array $cv, array $items, string $jobDescription, bool $requireExperience, array &$added, string $targetRole = '', bool $fillEverySection = false): array
    {
        if ($items === []) {
            return $cv;
        }

        $cv = $this->ensureKeySkills($cv, $items);

        for ($round = 1; $round <= 2; $round++) {
            $gaps = $this->coverageGaps($cv, $items, $requireExperience, $targetRole, $fillEverySection);
            if ($gaps === []) {
                return $cv;
            }

            Log::info('Tailored CV misses job requirements; asking the AI to revise', ['round' => $round, 'gaps' => $gaps]);
            try {
                $cv = $this->reviseForRequirements($cv, $items, $gaps, $jobDescription, $added);
            } catch (GeminiException $e) {
                Log::warning('Requirement revision failed', ['error' => $e->getMessage()]);

                return $cv;
            }
        }

        return $cv;
    }

    /**
     * Items in the list-like sections that the original CV does not support.
     *
     * @return string[]
     */
    private function unsupportedItems(array $cv, string $source): array
    {
        $items = [];
        foreach ((array) ($cv['skills'] ?? []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $skill) {
                $items[] = (string) $skill;
            }
        }
        foreach ((array) ($cv['projects'] ?? []) as $project) {
            $items[] = trim(($project['title'] ?? '').' '.($project['subtitle'] ?? ''));
            $items[] = (string) ($project['description'] ?? '');
        }
        foreach ((array) ($cv['certifications'] ?? []) as $certificate) {
            $items[] = (string) ($certificate['title'] ?? '');
            $items[] = (string) ($certificate['type'] ?? '');
            $items[] = (string) ($certificate['detail'] ?? '');
        }
        foreach ((array) ($cv['languages']['other'] ?? []) as $language) {
            $items[] = (string) ($language['name'] ?? '');
        }
        foreach ((array) ($cv['interests'] ?? []) as $interest) {
            $items[] = (string) ($interest['description'] ?? '');
        }
        $items[] = (string) ($cv['hobbies'] ?? '');
        foreach ((array) ($cv['education'] ?? []) as $entry) {
            $items[] = (string) ($entry['details'] ?? '');
        }

        return array_values(array_filter(array_map('trim', $items), fn (string $item) => $item !== '' && ! $this->guard->supports($source, $item)));
    }

    /**
     * A graduate's CV must not claim "Middle School completed" because a job asked for middle school.
     */
    private function dropLowerQualifications(array $cv): array
    {
        $degrees = implode(' ', array_map(fn ($entry) => (string) ($entry['degree'] ?? ''), (array) ($cv['education'] ?? [])));
        if (! preg_match('/bachelor|master|b\.?\s?sc|m\.?\s?sc|\bbs\b|\bms\b|\bba\b|\bma\b|b\.?com|mba|phd|degree|diploma|intermediate|f\.?sc/i', $degrees)) {
            return $cv;
        }

        $lower = '/[^.]*\b(middle school|primary school|elementary school|matric(ulation)?)\b[^.]*\.?\s*/i';
        foreach ((array) ($cv['education'] ?? []) as $index => $entry) {
            if (preg_match('/^\s*(middle|primary|elementary) school\b/i', (string) ($entry['degree'] ?? ''))) {
                unset($cv['education'][$index]);

                continue;
            }
            $cv['education'][$index]['details'] = trim(preg_replace($lower, '', (string) ($entry['details'] ?? '')) ?? '');
        }
        $cv['education'] = array_values($cv['education'] ?? []);
        $cv['about'] = trim(preg_replace($lower, '', (string) ($cv['about'] ?? '')) ?? '');
        foreach ((array) ($cv['skills'] ?? []) as $index => $group) {
            $cv['skills'][$index]['items'] = array_values(array_filter((array) ($group['items'] ?? []), fn ($item) => ! preg_match('/middle school|primary school|matric/i', (string) $item)));
        }
        $cv['skills'] = array_values(array_filter($cv['skills'] ?? [], fn ($group) => ! empty($group['items'])));

        return $cv;
    }

    /**
     * "Key skills for this role" comes first and lists every requirement.
     */
    private function ensureKeySkills(array $cv, array $items): array
    {
        $label = 'Key skills for this role';
        $groups = is_array($cv['skills'] ?? null) ? $cv['skills'] : [];
        $existing = [];
        foreach ($groups as $index => $group) {
            if (is_array($group) && strcasecmp(trim((string) ($group['label'] ?? '')), $label) === 0) {
                $existing = (array) ($group['items'] ?? []);
                unset($groups[$index]);
            }
        }

        // Availability and location are not skills: they get their own short group ("Night shift", "Full-time").
        $availabilityPattern = '/availab|shift|commut|relocat|full[- ]?time|part[- ]?time|immediate|notice period|weekend|willing to travel|based in|start date/i';
        $availability = [];
        foreach ($groups as $index => $group) {
            if (is_array($group) && strcasecmp(trim((string) ($group['label'] ?? '')), 'Availability') === 0) {
                $availability = (array) ($group['items'] ?? []);
                unset($groups[$index]);
            }
        }

        $merged = [];
        foreach ([...$items, ...$existing, ...$availability] as $item) {
            $item = trim((string) $item);
            $isAvailability = preg_match($availabilityPattern, $item) === 1;
            if ($isAvailability) {
                $item = trim(preg_replace('/\s*\bavailability\b\s*/i', ' ', $item) ?? $item);
                $item = mb_strtoupper(mb_substr($item, 0, 1)).mb_substr($item, 1);
            }
            $bucket = $isAvailability ? 'availability' : 'skills';
            $duplicate = false;
            foreach ($merged[$bucket] ?? [] as $kept) {
                if (mb_strtolower($kept) === mb_strtolower($item) || $this->mentions($kept, $item) || $this->mentions($item, $kept)) {
                    $duplicate = true;
                    break;
                }
            }
            if ($item !== '' && ! $duplicate) {
                $merged[$bucket][] = $item;
            }
        }

        $front = [];
        if (! empty($merged['skills'])) {
            $front[] = ['label' => $label, 'items' => $merged['skills']];
        }
        if (! empty($merged['availability'])) {
            $front[] = ['label' => 'Availability', 'items' => $merged['availability']];
        }
        $cv['skills'] = [...$front, ...array_values($groups)];

        return $cv;
    }

    /**
     * Which headings do not reflect the requirements yet.
     *
     * @return string[]
     */
    private function coverageGaps(array $cv, array $items, bool $requireExperience, string $targetRole = '', bool $fillEverySection = false): array
    {
        $headline = (string) ($cv['personal']['headline'] ?? '');
        $about = (string) ($cv['about'] ?? '');
        $experience = '';
        foreach ((array) ($cv['experience'] ?? []) as $entry) {
            $experience .= ' '.($entry['summary'] ?? '').' '.implode(' ', (array) ($entry['highlights'] ?? []));
        }

        $covered = fn (string $text) => array_values(array_filter($items, fn (string $item) => $this->mentions($text, $item)));
        $important = array_slice($items, 0, 3);

        $gaps = [];
        if (count(array_intersect($covered($headline), $important)) === 0) {
            $gaps[] = 'headline: must name at least one of: '.implode('; ', $important);
        }
        // Requirement labels pasted in word for word ("Night shift availability, Commute to Rawalpindi") read badly.
        $pasted = array_filter($items, fn (string $item) => str_contains($about, $item) || str_contains($headline, $item));
        if (count($pasted) >= 2) {
            $gaps[] = 'about and headline: the requirements are pasted as labels ('.implode('; ', $pasted).'); write them naturally';
        }
        if ($fillEverySection && empty($cv['interests'])) {
            $gaps[] = 'interests: empty; write one short line of interests that fit this job';
        }
        if ($fillEverySection && trim((string) ($cv['hobbies'] ?? '')) === '') {
            $gaps[] = 'hobbies: empty; write one short line of hobbies that fit this job';
        }
        $aboutMissing = array_values(array_diff($items, $covered($about)));
        if (count($aboutMissing) > max(1, intdiv(count($items), 3))) {
            $gaps[] = 'about: must mention '.implode('; ', $aboutMissing);
        }
        // Experience cannot show availability items (night shifts, commuting), so it should reflect the target role
        // itself: at least one highlight that names the kind of work the job is about.
        $roleWords = array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower($targetRole)) ?: [], fn ($w) => mb_strlen($w) > 3
            && ! in_array($w, ['representative', 'specialist', 'officer', 'assistant', 'executive', 'senior', 'junior', 'agent', 'associate', 'level', 'entry'], true));
        $reflectsRole = $roleWords !== [] && $this->mentions($experience, implode(' ', array_slice(array_values($roleWords), 0, 1)));
        if ($requireExperience && ! $reflectsRole && $covered($experience) === []) {
            $gaps[] = 'experience: each role needs one highlight showing work related to '.($targetRole ?: 'this job').' (for example dealing with clients, customers or users)';
        }

        return $gaps;
    }

    /**
     * True when most meaningful words of the requirement appear in the text (so "Night shift availability"
     * matches "available for night shifts").
     */
    private function mentions(string $text, string $item): bool
    {
        $stem = fn (string $word) => mb_substr($word, 0, 5);
        $words = array_filter(preg_split('/[^\p{L}\p{N}+#]+/u', mb_strtolower($item)) ?: [], fn ($w) => mb_strlen($w) > 2
            && ! in_array($w, ['and', 'the', 'for', 'with', 'availability', 'experience', 'ability', 'skills', 'year', 'years'], true));
        if ($words === []) {
            return str_contains(mb_strtolower($text), mb_strtolower($item));
        }

        $textStems = array_map($stem, preg_split('/[^\p{L}\p{N}+#]+/u', mb_strtolower($text)) ?: []);
        $hits = count(array_filter($words, fn ($w) => in_array($stem($w), $textStems, true)));

        return $hits / count($words) >= 0.5;
    }

    /**
     * Ask the AI to fix exactly the headings that miss the requirements.
     *
     * @throws GeminiException
     */
    private function reviseForRequirements(array $cv, array $items, array $gaps, string $jobDescription, array &$added): array
    {
        $current = json_encode([
            'headline' => $cv['personal']['headline'] ?? '',
            'about' => $cv['about'] ?? '',
            'experience' => array_map(fn ($entry) => [
                'title' => $entry['title'] ?? '',
                'company' => $entry['company'] ?? '',
                'highlights' => array_values((array) ($entry['highlights'] ?? [])),
            ], (array) ($cv['experience'] ?? [])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $itemList = '- '.implode("\n- ", $items);
        $gapList = '- '.implode("\n- ", $gaps);
        $job = Str::limit($jobDescription, 4000, '');

        $prompt = <<<PROMPT
You are revising part of a CV so it clearly fits the job below. The candidate's REQUIREMENTS list is true for them and must be reflected in the CV.

Fix these problems:
{$gapList}

Rules:
- headline: one line (max 110 characters) aimed at the target job that names the most important requirements in natural words.
- about: 3-4 fluent sentences a recruiter would enjoy reading, written for this job, mentioning every requirement naturally (e.g. "available for full-time night shifts and able to commute to Rawalpindi"). Never paste requirements as a capitalised list.
- interests / hobbies (only when asked to fix them): one short natural line that fits the job.
- experience: keep every existing highlight; if a role has no highlight related to the job, add ONE short highlight showing related work in that role (communication, clients, customers, teamwork, reliability) without new numbers, dates, employers or job titles.
- Never invent employers, job titles, dates, degrees, certifications or numbers.
Return ONLY JSON: {"headline": "...", "about": "...", "experience": [["highlight", "..."], ...one array per role in the same order...], "interests": "...", "hobbies": "..."}

REQUIREMENTS:
{$itemList}

CURRENT CV PARTS:
{$current}

JOB:
{$job}
PROMPT;

        $revised = $this->generateJson([['text' => $prompt]], 30, budget: 35);

        $headline = $this->textValue($revised['headline'] ?? '');
        if ($headline !== '') {
            $cv['personal']['headline'] = Str::limit($headline, 160, '');
            $added[] = $cv['personal']['headline'];
        }
        $about = $this->textValue($revised['about'] ?? '');
        if ($about !== '') {
            $cv['about'] = $about;
        }

        foreach (['interests', 'hobbies'] as $field) {
            $value = $this->textValue($revised[$field] ?? '');
            if ($value === '') {
                continue;
            }
            if ($field === 'interests' && empty($cv['interests'])) {
                $cv['interests'] = [['title' => '', 'description' => $value]];
                $added[] = $value;
            } elseif ($field === 'hobbies' && trim((string) ($cv['hobbies'] ?? '')) === '') {
                $cv['hobbies'] = $value;
                $added[] = $value;
            }
        }

        foreach ((array) ($revised['experience'] ?? []) as $index => $highlights) {
            if (! isset($cv['experience'][$index]) || ! is_array($highlights)) {
                continue;
            }
            $before = array_map('mb_strtolower', (array) ($cv['experience'][$index]['highlights'] ?? []));
            $clean = array_values(array_filter(array_map(fn ($h) => $this->textValue($h), $highlights)));
            if ($clean === []) {
                continue;
            }
            foreach ($clean as $highlight) {
                if (! in_array(mb_strtolower($highlight), $before, true)) {
                    $added[] = $highlight;
                }
            }
            $cv['experience'][$index]['highlights'] = $clean;
        }

        return $cv;
    }

    /**
     * The facts a candidate confirmed for an application, as text that is stored and checked with the CV.
     *
     * @param  string[]  $confirmed
     */
    public static function confirmedBlock(array $confirmed): string
    {
        return $confirmed === [] ? '' : "Confirmed by the candidate for this application:\n- ".implode("\n- ", $confirmed);
    }

    /**
     * @return array{level: string, target_role: string, company: string, missing: string[], note: string}
     */
    private function normalizeFit(mixed $fit): array
    {
        $fit = is_array($fit) ? $fit : [];
        $level = strtolower($this->textValue($fit['level'] ?? ''));

        return [
            'level' => in_array($level, ['strong', 'partial', 'weak'], true) ? $level : 'partial',
            'target_role' => Str::limit($this->textValue($fit['target_role'] ?? ''), 120, ''),
            'company' => Str::limit($this->textValue($fit['company'] ?? ''), 120, ''),
            'missing' => array_slice(array_values(array_filter(array_map(
                fn ($item) => Str::limit($this->textValue($item), 80, ''),
                is_array($fit['missing'] ?? null) ? $fit['missing'] : []
            ))), 0, 6),
            'note' => Str::limit($this->textValue($fit['note'] ?? ''), 300, ''),
        ];
    }

    /**
     * AI review of the tailored CV's sentences against the original CV, followed by the deterministic checks
     * again. Never throws: when the review cannot run, the CV is returned unchanged and marked unverified.
     *
     * @return array{cv: array, factCheck: array{verified: bool, removed: string[]}}
     */
    public function factCheckCv(array $cv, string $source, string $jobDescription): array
    {
        if (mb_strlen($source) < self::MIN_SOURCE_FOR_CHECKS) {
            Log::warning('CV fact check skipped: not enough source text');

            return ['cv' => $cv, 'factCheck' => ['verified' => false, 'removed' => []]];
        }

        try {
            $review = $this->verifyClaims($this->guard->claims($cv), $source, $jobDescription);
            $checked = $this->guard->applyVerdicts($cv, $review['verdicts'], $source);
        } catch (GeminiException $e) {
            Log::warning('CV fact check by AI failed; deterministic checks only', ['error' => $e->getMessage()]);

            return ['cv' => $cv, 'factCheck' => ['verified' => false, 'removed' => []]];
        }

        if ($checked['removed'] !== []) {
            Log::info('CV fact check corrected the tailored CV', ['changes' => count($checked['removed'])]);
        }

        return [
            'cv' => $checked['cv'],
            'factCheck' => ['verified' => $review['complete'], 'removed' => array_values(array_unique($checked['removed']))],
        ];
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

Be strict about FACTS. Wording that comes from the JOB DESCRIPTION but is not backed by the ORIGINAL CV is unsupported. A job title or degree name is supported only if the ORIGINAL CV gives it (small wording differences are fine).

REFRAMING IS ALLOWED: describing facts the ORIGINAL CV states in terms of the transferable skill they show is supported, so "keep" it. Examples: "worked with project managers and client teams" supports "client communication" and "stakeholder communication"; "mentored two junior developers" supports "training and coaching others"; "wrote technical documentation" supports "clear written communication"; "English (C1)" supports "fluent English"; "handled refunds and complaints" supports "complaint handling". A neutral headline or summary that only combines such supported points is also "keep".
Do NOT allow new facts: roles, industries, experience types (e.g. "call-centre experience", "sales experience"), tools, numbers, availability (shifts, start dates, relocation) or achievements the ORIGINAL CV does not state.

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
        // Capped so a struggling AI service gives a "please review" result instead of a two-minute wait.
        $response = $this->generateJson([['text' => $prompt]], 35, budget: 45);
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
     * List what a job asks for, in any field (not only technical skills).
     *
     * @return array<int, array{name: string, type: string, importance: string}>
     *
     * @throws GeminiException
     */
    public function extractRequirements(string $jobDescription): array
    {
        $jobDescription = Str::limit($jobDescription, 8000, '');

        $prompt = <<<PROMPT
Read this job description and list what the employer requires or prefers from candidates. Return ONLY JSON:
{"requirements": [{"name": "short label, at most 5 words", "type": "skill|experience|qualification|language|availability|other", "importance": "required|preferred"}]}

Rules:
- 4 to 12 items, most important first.
- Concrete and checkable, in the job's own field. Examples: "Fluent spoken English", "Inbound call handling", "Sales experience", "Night shift availability", "Laravel", "2+ years PHP", "Bachelor's degree", "Customer complaint handling".
- Merge duplicates. Skip salary, benefits, company description and the office address.
- Skip vague filler such as "hard working" or "team player" unless the job stresses it.

JOB DESCRIPTION:
{$jobDescription}
PROMPT;

        $requirements = [];
        foreach ((array) ($this->generateJson([['text' => $prompt]], 25, budget: 30)['requirements'] ?? []) as $item) {
            $name = Str::limit($this->textValue(is_array($item) ? ($item['name'] ?? '') : $item), 60, '');
            if ($name === '' || isset($requirements[strtolower($name)])) {
                continue;
            }
            $requirements[strtolower($name)] = [
                'name' => $name,
                'type' => $this->textValue(is_array($item) ? ($item['type'] ?? 'other') : 'other') ?: 'other',
                'importance' => strtolower($this->textValue(is_array($item) ? ($item['importance'] ?? '') : '')) === 'preferred' ? 'preferred' : 'required',
            ];
        }

        if ($requirements === []) {
            throw new GeminiException('No requirements were found in the job description.');
        }

        return array_slice(array_values($requirements), 0, 12);
    }

    /**
     * Decide which requirements a CV shows. The CV is given as text, or as the original file when its text
     * could not be read.
     *
     * @param  array<int, array{name: string}>  $requirements
     * @param  array{mime_type: string, data: string}|null  $cvFile
     * @return array<int, array{met: bool, evidence: string}> in the same order as $requirements
     *
     * @throws GeminiException
     */
    public function assessRequirements(array $requirements, string $cvText, ?array $cvFile = null): array
    {
        $list = [];
        foreach (array_values($requirements) as $i => $requirement) {
            $list['r'.($i + 1)] = $requirement['name'];
        }
        $listJson = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cv = trim($cvText) !== '' ? Str::limit($cvText, 14000, '') : '[The CV is attached as a file. Read it directly.]';

        $prompt = <<<PROMPT
For each job requirement, decide whether the candidate's CV shows it. Return ONLY JSON:
{"results": [{"id": "r1", "met": true, "evidence": "a few words from the CV that show it, or empty"}]}

Rules:
- "met" is true only when the CV states it or clearly demonstrates it. Examples: "handled 80 calls per night shift" demonstrates "Inbound call handling" and "Night shift availability"; "English (C1)" demonstrates "Fluent English"; "3 years with Laravel" demonstrates "2+ years PHP".
- Experience from a different field counts only when it truly demonstrates the requirement.
- Availability (shifts, start date, relocation) is met only when the CV says so.
- Include every id exactly once.

REQUIREMENTS:
{$listJson}

CV:
{$cv}
PROMPT;

        $parts = [['text' => $prompt]];
        if ($cvFile && trim($cvText) === '') {
            $parts[] = ['inline_data' => ['mime_type' => $cvFile['mime_type'], 'data' => $cvFile['data']]];
        }

        $byId = [];
        foreach ((array) ($this->generateJson($parts, 25, budget: 30)['results'] ?? []) as $result) {
            if (is_array($result) && isset($list[(string) ($result['id'] ?? '')])) {
                $byId[(string) $result['id']] = [
                    'met' => filter_var($result['met'] ?? false, FILTER_VALIDATE_BOOL),
                    'evidence' => Str::limit($this->textValue($result['evidence'] ?? ''), 120, ''),
                ];
            }
        }

        if (count($byId) !== count($list)) {
            throw new GeminiException('The requirement check was incomplete.');
        }

        return array_map(fn (string $id) => $byId[$id], array_keys($list));
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

        $job = $this->generateJson([['text' => $prompt]], 45);

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

        $email = $this->generateJson([['text' => $prompt]], 45);
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
     * Models are tried in the configured order (fastest first), except that a model which recently failed for
     * everyone (overloaded, hung or retired) is tried last for a couple of minutes.
     *
     * @param  int  $timeout  seconds to wait for one model's answer
     * @param  int  $budget  seconds this call may spend in total across keys and models
     *
     * @throws GeminiException
     */
    private function generateJson(array $parts, int $timeout, int $budget = self::TIME_BUDGET): array
    {
        if (! $this->isConfigured()) {
            throw new GeminiException('Gemini API key is not configured. Set GEMINI_API_KEY in .env.');
        }

        $generationConfig = ['responseMimeType' => 'application/json'];
        if ($this->thinkingLevel !== '') {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $this->thinkingLevel];
        }

        $payload = ['contents' => [['parts' => $parts]], 'generationConfig' => $generationConfig];
        $cooling = array_filter($this->models, fn (string $model) => $this->cooldowns()->has($this->cooldownKey($model)));
        $models = [...array_diff($this->models, $cooling), ...$cooling];
        $deadline = microtime(true) + $budget;
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

                $startedAt = microtime(true);
                try {
                    $response = Http::timeout(min($timeout, $remaining))
                        ->connectTimeout(10)
                        ->withHeaders(['X-goog-api-key' => $apiKey])
                        ->post(self::API_BASE.$model.':generateContent', $payload);
                } catch (ConnectionException $e) {
                    $lastStatus = 'timeout';
                    $this->coolDown($model);
                    Log::warning('Gemini request failed to connect', ['model' => $model, 'key' => $keyIndex + 1, 'error' => $e->getMessage()]);

                    continue;
                }

                if (! $response->successful()) {
                    $lastStatus = $response->status();
                    Log::warning('Gemini request rejected', [
                        'model' => $model,
                        'key' => $keyIndex + 1,
                        'status' => $lastStatus,
                        'reason' => Str::limit((string) $response->json('error.message'), 160),
                    ]);

                    if ($this->isModelWideFailure($lastStatus)) {
                        $this->coolDown($model);
                    }

                    if ($lastStatus === 400 && $this->isKeyProblem($response)) {
                        $lastStatus = 401;
                    } elseif ($lastStatus === 400) {
                        // The request itself is invalid (e.g. an unreadable file); every key and model would reject it.
                        $lastStatus = 'bad-request';
                        break 2;
                    }

                    continue;
                }

                $text = $response->json('candidates.0.content.parts.0.text');
                $decoded = is_string($text) ? json_decode($this->extractJson($text), true) : null;

                if (is_array($decoded)) {
                    $this->cooldowns()->forget($this->cooldownKey($model));
                    Log::info('Gemini call', [
                        'model' => $model,
                        'seconds' => round(microtime(true) - $startedAt, 1),
                        'input_tokens' => $response->json('usageMetadata.promptTokenCount'),
                        'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
                        'thinking_tokens' => $response->json('usageMetadata.thoughtsTokenCount'),
                    ]);

                    return $decoded;
                }

                $lastStatus = 'invalid-json';
                Log::warning('Gemini returned unusable output', [
                    'model' => $model,
                    'finish_reason' => $response->json('candidates.0.finishReason'),
                ]);
            }
        }

        throw new GeminiException($this->failureMessage($lastStatus), $lastStatus === 'bad-request' ? 400 : 0);
    }

    private function coolDown(string $model): void
    {
        $this->cooldowns()->put($this->cooldownKey($model), true, self::COOLDOWN_SECONDS);
    }

    /**
     * Local file cache: this is checked on every Gemini call, so it must not cost a database round trip.
     */
    private function cooldowns(): Repository
    {
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }

    private function cooldownKey(string $model): string
    {
        return 'gemini-cooldown:'.$model;
    }

    /**
     * Gemini answers an invalid or expired API key with HTTP 400 too; that one is worth trying the next key for.
     */
    private function isKeyProblem(Response $response): bool
    {
        $reasons = array_column(array_filter((array) $response->json('error.details', []), 'is_array'), 'reason');

        return in_array('API_KEY_INVALID', $reasons, true)
            || str_contains(strtolower((string) $response->json('error.message')), 'api key');
    }

    private function isModelWideFailure(int|string|null $status): bool
    {
        // 404: the model is retired or unavailable to this project; other keys will not help.
        return $status === 'timeout' || $status === 404 || (is_int($status) && $status >= 500);
    }

    private function failureMessage(int|string|null $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => 'All Gemini API keys were rejected. Check GEMINI_API_KEY in .env.',
            $status === 429 => 'Gemini rate limit reached on every configured key. Wait a minute and try again.',
            $status === 'timeout' => 'Gemini did not respond in time. Check your internet connection and try again.',
            $status === 'invalid-json' => 'The AI returned an unreadable answer. Please try again.',
            $status === 'bad-request' => 'The AI could not read this CV file. Please upload it again as a PDF, DOCX or TXT file.',
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
        bool $wantsTranscript = false,
        array $confirmed = [],
        bool $fillGaps = false
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
        $addedShape = $fillGaps ? ",\n  \"added\": [\"every sentence, highlight, skill or phrase you added that the uploaded CV does not state, copied exactly as it appears in the cv JSON\"]" : '';
        $fillGapsRule = ! $fillGaps ? '' : <<<'RULE'


JOB-FIRST MODE IS ON. The candidate wants the CV built around this job. Where it conflicts with the truth rule or the completeness rule, this mode wins:
- Make the CV show EVERY requirement of the job. Put the most important ones in the headline and "about", list all of them in a skills group labelled "Key skills for this role" (first), and where it fits an existing role, add one highlight describing related work in that role (e.g. for a call-centre job: "Handled client calls and emails to resolve their questions").
- Write the headline and "about" for the target job, as a candidate for that job would.
- For a "weak" fit, cut the old field's technical detail down to one short line per role and one compact "Technical background" skills group at the end.
- FILL EVERY SECTION of the template; never leave one empty. Rewrite every section for this job:
  - personal.headline and "about": written for the target job.
  - experience: keep every role, employer, title and date; rewrite each role's summary and highlights for this job (2-4 highlights per role).
  - education: keep every entry and keep its grade, CGPA, honours and status (e.g. "CGPA: 3.68 / 4.0, currently in 7th semester") exactly; you may add one short job-relevant note after them.
  - projects: KEEP EVERY project, startup, competition and pitch from the CV (same title, e.g. a Hult Prize entry), count them before you answer, and rewrite each description for this job; only if the CV has no projects at all, add 1-2 short job-relevant activities (e.g. volunteering, a university project) with no dates or numbers.
  - skills: "Key skills for this role" first, then the job's tools and soft skills, then any background skills.
  - languages: keep the CV's languages; add the language the job needs if missing, with a level only if the CV gives one.
  - certifications: KEEP EVERY certificate from the CV; if none fits this job, ADD 1-2 well-known courses that fit it as SUGGESTIONS: title = course name, type = provider, detail = "Suggested – replace with your own".
  - Never drop a real item from education, projects, certifications or languages: rewrite, reorder, but keep.
  - interests and hobbies: one short line each that fits the job (both must be filled).
  - An education requirement is met by any equal or higher qualification: never add a lower one (e.g. never "Middle School completed" for a graduate).
  - Write requirements into fluent sentences; never paste them as a capitalised list.
- Never invent employers, job titles, dates, degrees, grades, links, or any number, percentage or metric.
- List EVERY sentence, highlight, skill or item you added that the uploaded CV does not state in "added", copied exactly as it appears in the cv JSON. The candidate reviews this list and can change anything with Edit.
RULE;
        $confirmedRule = $confirmed === [] ? '' : "\n\nCANDIDATE CONFIRMED (the candidate states these are true for them; treat them as facts from the CV):\n- "
            .implode("\n- ", $confirmed)
            ."\nEvery confirmed item must appear in the tailored CV: in the headline or about section when it matters most for this job, and in a skills group labelled \"Key skills for this role\". Write each one naturally inside sentences (e.g. \"available for full-time night shifts and able to commute to Rawalpindi\"), never as a pasted list of capitalised labels, and without adding employers, dates or numbers the candidate did not give. An education requirement is met by any equal or higher qualification: never add a lower one (e.g. never \"Middle School completed\" for a graduate). Confirmed items count as CV evidence when you judge the fit.";

        return <<<PROMPT
You are tailoring a candidate's CV for a job. Tailoring means SELECTING, ORDERING, REWORDING and EMPHASISING what the CV already says so the most relevant facts stand out. It never means adding anything.

Return ONLY valid JSON, no markdown fences or comments, in exactly this shape:
{
  "fit": {
    "level": "strong" | "partial" | "weak",
    "target_role": "the job title from the job description",
    "company": "the hiring company from the job description, or empty",
    "missing": ["requirements of the job that the CV does not show (max 6, short)"],
    "note": "one sentence to the candidate, in the second person (\"your CV...\"), about how well the CV fits this job"
  },
  "cv": { ...CV JSON using the provided schema... },
  "reasoning": "Brief summary of what was emphasised or reordered and why"{$transcriptShape}{$addedShape}
}

FIRST, JUDGE THE FIT between the CV and the job:
- "strong": the candidate's experience is in the same field and covers most requirements.
- "partial": same or neighbouring field, but important requirements are missing.
- "weak": a different field (e.g. a software developer applying for a call-centre or sales job).

THE TRUTH RULE (overrides every other instruction):
- The uploaded CV is the ONLY source of facts about the candidate. {$fileNote}
- The job description is a list of what the employer wants. It is NOT evidence about the candidate. Never copy its phrases into the candidate's experience, summary or skills unless the CV states the same fact. Example: if the job says "build our SaaS platform" and the CV says "maintained WordPress sites", do NOT write "SaaS".
- Never add or upgrade: employers, job titles, seniority or leadership ("led", "senior", "managed", "mentored", "architected", "scaled"), responsibilities, technologies, skills, tools, domains or industries, clients, team sizes, numbers, percentages, metrics, outcomes, dates, degrees, grades, certifications, links, nationality, date of birth, or language levels.
- Every field you fill must be stated in the CV. If the CV does not state something, leave that field as an empty string or empty array. An empty field is always better than a guessed one. This applies especially to business_sector, department, location, website, link, date_of_birth, nationality and language levels.
- Only set languages.mother when the CV explicitly says which language is the mother tongue or native language. Otherwise list every language under "other" with empty levels unless the CV gives levels.
- Keep each job title exactly as the CV gives it.
- If a skill the job asks for is not in the CV, leave it out. Missing skills are shown to the user separately; your job is not to hide gaps.{$transcriptRule}{$confirmedRule}{$fillGapsRule}

THE COMPLETENESS RULE (for "strong" and "partial" fit; as important as the truth rule):
- The tailored CV must contain EVERYTHING the uploaded CV contains. Keep every job, every bullet point and achievement, every number and metric, every named product, client, tool, technology, team size, link, grade, project, certificate, language and interest.
- Tailoring changes ORDER, EMPHASIS and WORDING only. Never drop or generalise a fact because it seems less relevant: keep "Integrated Stripe and JazzCash" as it is, never "integrated payment gateways".
- One CV bullet point becomes at least one highlight. You may split a long point, never merge points away.
- Every skill named anywhere in the CV goes into the skills section.
- The tone changes how things are worded, not what is included.

CAREER-CHANGE MODE (for "weak" fit, replaces the completeness rule; the truth rule still applies):
- Reframe the SAME true facts for the target role, so the CV reads as a strong application for THIS job, not for the candidate's old field.
- personal.headline: a short line for the target role built only from CV facts, e.g. "Customer-focused professional · Fluent English (C1) · 5 years working with clients and teams". Never claim the target job title or experience the CV does not show.
- "about": lead with what this job needs that the CV truly shows (language level, communication, working with clients or customers, teamwork, reliability, training others, documentation, problem solving), then one sentence on the background.
- Skills: put the transferable skills this job needs first, in a group labelled for the job (e.g. "Communication & Customer Skills"), using only skills the CV shows or clearly evidences (e.g. "mentored two developers" evidences "training others"). Put the old field's tools last in one compact group (e.g. "Technical background").
- Experience: keep EVERY job with its employer, title and dates (never create gaps). Rewrite each role to lead with the transferable parts (dealing with clients, project managers or users; explaining things; teamwork; deadlines; responsibility). Keep at most 2-3 highlights per role and condense purely technical detail that this job does not care about into one short line.
- Keep education, certifications, languages and interests. Languages often matter most in these roles, so keep levels exactly as the CV gives them.

HOW TO TAILOR (within the rules above):
- Put the experience, projects and skills most relevant to the job first, and use the job's terminology only where it names the same thing the CV does (e.g. CV "REST APIs" may be written as "REST API development").
- Rewrite highlights so the job-relevant facts stand out, leading with the action and the result.
- For each role write a one-sentence "summary" of what the CV says the person did there (scope, team, product), using only CV facts.
- "about" is a 3-4 sentence professional summary built from the CV, leading with what matters most for this job.
- Group skills like the template ("Technical", "Tools", "Professional", "Soft skills"), each group an items array.
- Include every section the CV has (languages, certifications, projects, driving licence, hobbies, interests), even when not relevant to the job. Do not create items for sections the CV does not have.
- Do not copy anything from the empty schema's sample values.

WHERE THINGS GO:
- personal.headline: one short line under the name, aimed at the target job, made only of CV facts (for every fit level; e.g. "Senior Laravel Developer · 5 years building payment APIs").
- personal.linkedin, personal.github, personal.website: profile links exactly as written in the CV.
- personal.address: city and country from the CV.
- education.details: grades, thesis or final-year project, honours, relevant modules. education.location: the school's city if given.
- languages.other[].listening/reading/speaking_production/speaking_interaction/writing: use the CEFR level (A1-C2) the CV gives for that language for every column; leave empty if the CV gives no level. "Native" languages go in languages.mother (comma-separated if several).
- certifications: title = certificate name, type = issuing organisation, detail = date or other detail.
- projects: title, subtitle = type or technologies, period, description, link.

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
- About section of 2-3 focused sentences.
- Short, punchy highlights: action and result, no filler words.
- Remove repeated wording, but keep every fact, role and achievement (the completeness rule still applies).
TEXT,
            'detailed' => <<<TEXT
Style: Detailed.
- A richer about section (4 sentences) with clear job alignment.
- Fuller highlights that add the context the CV gives (what was built, for whom, with which tools, the result).
- Use the job's terminology where the CV describes the same thing.
- Be specific using only details the CV gives; never fill gaps.
TEXT,
            default => <<<TEXT
Style: Professional.
- Balanced, polished, recruiter-friendly language.
- Clear, confident and specific highlights that keep the CV's details (names, tools, numbers).
- Use the job's terminology only where the CV describes the same thing.
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
                'headline' => '',
                'linkedin' => '',
                'github' => '',
                'website' => '',
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
        $normalized['details'] = $this->firstText($entry, ['details', 'description', 'summary', 'notes', 'field', 'field_of_study']);

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
