<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiException;
use App\Services\ApplicationContact;
use App\Services\CvContent;
use App\Services\CvUploadParser;
use App\Services\GeminiService;
use App\Services\SkillMatcher;
use App\Services\TailoredCvRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CvController extends Controller
{
    private const THEMES = [
        'purple' => ['primary' => '#5C2D91', 'primary_light' => '#7B4DB5'],
        'blue' => ['primary' => '#1D4ED8', 'primary_light' => '#60A5FA'],
        'green' => ['primary' => '#047857', 'primary_light' => '#34D399'],
        'teal' => ['primary' => '#0F766E', 'primary_light' => '#2DD4BF'],
        'red' => ['primary' => '#B91C1C', 'primary_light' => '#F87171'],
        'dark' => ['primary' => '#1F2937', 'primary_light' => '#6B7280'],
        'gold' => ['primary' => '#A16207', 'primary_light' => '#FBBF24'],
    ];

    /** File types Gemini can read directly, sent alongside the extracted text. */
    private const GEMINI_FILE_TYPES = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
    ];

    public function __construct(private readonly TailoredCvRepository $results)
    {
    }

    public function tailor(Request $request, CvUploadParser $parser, GeminiService $gemini, SkillMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'jobDescription' => ['required', 'string', 'min:50', 'max:30000'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx,txt,html,htm', 'max:10240'],
            'template' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_-]+\.json$/'],
            'tone' => ['nullable', 'string', 'in:professional,concise,detailed'],
            'job' => ['nullable', 'array'],
            'job.url' => ['nullable', 'url', 'max:2048'],
            'job.title' => ['nullable', 'string', 'max:255'],
            'job.company' => ['nullable', 'string', 'max:255'],
            'job.location' => ['nullable', 'string', 'max:255'],
            'job.applyUrl' => ['nullable', 'url', 'max:2048'],
            'job.emails' => ['nullable', 'array', 'max:10'],
            'job.emails.*' => ['email'],
        ]);

        set_time_limit(180);

        $layout = $this->loadLayoutTemplate($validated['template'] ?? 'default.json');
        if ($layout === null) {
            return response()->json(['message' => 'Could not load the selected CV template.'], 422);
        }

        $uploadedFile = $request->file('cv');
        $cvText = $parser->extract($uploadedFile);
        $filePart = $this->geminiFilePart($uploadedFile);

        if (Str::length($cvText) < 80 && ! $filePart) {
            return response()->json([
                'message' => 'Could not read enough text from the uploaded CV. Please upload a text-based PDF, DOCX, HTML, or TXT CV.',
            ], 422);
        }

        try {
            $tailored = $gemini->tailorUploadedCvContent(
                $validated['jobDescription'],
                $cvText,
                $layout,
                $validated['tone'] ?? 'professional',
                $filePart
            );
        } catch (GeminiException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $cv = [...$tailored['cv'], 'theme' => $this->theme('purple')];
        $job = $this->jobContext($validated);
        // Pasted job text has no title or company: use what the AI read from the description.
        $job['title'] = $job['title'] ?: $tailored['fit']['target_role'];
        $job['company'] = $job['company'] ?: $tailored['fit']['company'];
        $token = $this->results->create($cv, $job, $tailored['source']);

        Log::info('CV tailored', [
            'result' => $token,
            'job' => $job['title'] ?: $job['url'],
            'corrections' => count($tailored['removed']),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->resultUrls($token),
                'result' => $token,
                'reasoning' => $tailored['reasoning'],
                // Removed by the instant checks; the AI review follows via cv.fact-check.
                'removed' => $tailored['removed'],
                'fit' => $tailored['fit'],
                'match' => $matcher->match($validated['jobDescription'], $this->searchableText($cv)),
                'application' => ApplicationContact::resolve($job),
            ],
        ]);
    }

    /**
     * Second stage of tailoring: the AI reviews every rewritten sentence against the original CV.
     */
    public function factCheck(Request $request, GeminiService $gemini, SkillMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
        ]);

        set_time_limit(90);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV not found. Please generate it again.'], 404);
        }

        $description = (string) ($record['job']['description'] ?? '');
        $checked = $gemini->factCheckCv($record['cv'], $record['source'], $description);

        if ($checked['cv'] !== $record['cv']) {
            $this->results->updateCv($validated['result'], $checked['cv']);
        }

        Log::info('CV fact-checked', [
            'result' => $validated['result'],
            'verified' => $checked['factCheck']['verified'],
            'corrections' => count($checked['factCheck']['removed']),
        ]);

        return response()->json([
            'success' => true,
            ...$this->resultUrls($validated['result']),
            'factCheck' => $checked['factCheck'],
            'match' => $description !== '' ? $matcher->match($description, $this->searchableText($checked['cv'])) : null,
        ]);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
            'includePhoto' => ['required', 'boolean'],
            'photo' => ['nullable', 'required_if:includePhoto,1', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV result not found.'], 404);
        }

        $cv = $record['cv'];
        $personal = is_array($cv['personal'] ?? null) ? $cv['personal'] : [];
        unset($personal['photo'], $personal['profile_photo']);

        if ($request->boolean('includePhoto')) {
            $photo = $request->file('photo');
            $personal['photo'] = 'data:'.$photo->getMimeType().';base64,'.base64_encode($photo->get());
        }

        $this->results->updateCv($validated['result'], [...$cv, 'personal' => $personal]);

        return response()->json(['success' => true, ...$this->resultUrls($validated['result'])]);
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
            'theme' => ['required', 'string', 'in:'.implode(',', array_keys(self::THEMES))],
        ]);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV result not found.'], 404);
        }

        $this->results->updateCv($validated['result'], [...$record['cv'], 'theme' => $this->theme($validated['theme'])]);

        return response()->json(['success' => true, ...$this->resultUrls($validated['result'])]);
    }

    /**
     * The tailored CV's text, for the editor.
     */
    public function content(Request $request, CvContent $content): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
        ]);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV not found. Please generate it again.'], 404);
        }

        return response()->json(['cv' => $content->editable($record['cv'])]);
    }

    /**
     * Save the user's edits. The user is the authority on their own CV, so edits are not fact-checked,
     * only cleaned to the template schema.
     */
    public function updateContent(Request $request, CvContent $content): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
            'cv' => ['required', 'array'],
        ]);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV not found. Please generate it again.'], 404);
        }

        $cv = $content->merge($record['cv'], $validated['cv']);
        if (trim((string) data_get($cv, 'personal.name')) === '') {
            return response()->json(['message' => 'Your name cannot be empty.', 'errors' => ['cv.personal.name' => ['Your name cannot be empty.']]], 422);
        }

        $this->results->updateCv($validated['result'], $cv);

        return response()->json([
            'success' => true,
            ...$this->resultUrls($validated['result']),
            'match' => $this->matchFor($record['job'], $cv),
        ]);
    }

    private function matchFor(array $job, array $cv): ?array
    {
        $description = (string) ($job['description'] ?? '');

        return $description !== '' ? app(SkillMatcher::class)->match($description, $this->searchableText($cv)) : null;
    }

    private function resultUrls(string $token): array
    {
        return [
            'previewUrl' => route('cv.share', ['result' => $token], false),
            'downloadUrl' => route('cv.download', ['result' => $token], false),
        ];
    }

    private function theme(string $name): array
    {
        return ['name' => $name, ...self::THEMES[$name]];
    }

    private function jobContext(array $validated): array
    {
        $job = $validated['job'] ?? [];

        return [
            'url' => (string) ($job['url'] ?? ''),
            'title' => (string) ($job['title'] ?? ''),
            'company' => (string) ($job['company'] ?? ''),
            'location' => (string) ($job['location'] ?? ''),
            'applyUrl' => (string) ($job['applyUrl'] ?? ''),
            'emails' => ApplicationContact::emails(implode(' ', $job['emails'] ?? []).' '.$validated['jobDescription']),
            'description' => $validated['jobDescription'],
        ];
    }

    private function loadLayoutTemplate(string $template): ?array
    {
        $path = public_path('cv_data/'.basename($template));
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    private function geminiFilePart(UploadedFile $file): ?array
    {
        $mimeType = self::GEMINI_FILE_TYPES[strtolower($file->getClientOriginalExtension())] ?? null;
        $bytes = $mimeType ? $file->get() : null;

        if (! $bytes) {
            return null;
        }

        return [
            'mime_type' => $mimeType,
            'data' => base64_encode($bytes),
            'name' => $file->getClientOriginalName(),
        ];
    }

    private function searchableText(array $cv): string
    {
        $parts = [];
        array_walk_recursive($cv, function ($value, $key) use (&$parts) {
            if (is_scalar($value) && $key !== 'photo') {
                $parts[] = (string) $value;
            }
        });

        return implode(' ', $parts);
    }
}
