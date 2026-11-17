<?php

namespace App\Services;

use App\Exceptions\GeminiException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * How well a CV covers what a job asks for, for jobs in any field.
 *
 * The job's requirements are read by the AI once per job description and cached, and every CV (the uploaded
 * one before tailoring, the tailored one after) is checked against that same list, so before/after scores are
 * comparable. Without the AI it falls back to keyword matching of technical skills.
 */
class RequirementMatcher
{
    private const CACHE_VERSION = 'v1';

    private const CACHE_SECONDS = 86400;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly SkillMatcher $keywords,
    ) {
    }

    /**
     * @param  array{mime_type: string, data: string}|null  $cvFile  the original file, used when $cvText is empty
     * @return array{score: ?int, matched: string[], missing: string[], required: string[], requirements: array, source: string}
     */
    public function match(string $jobDescription, string $cvText, ?array $cvFile = null): array
    {
        try {
            $requirements = $this->cache()->remember(
                $this->key('requirements', $jobDescription),
                self::CACHE_SECONDS,
                fn () => $this->gemini->extractRequirements($jobDescription)
            );

            $cvKey = trim($cvText) !== '' ? $cvText : ($cvFile['data'] ?? '');
            $assessment = $this->cache()->remember(
                $this->key('assessment', $jobDescription.'|'.$cvKey),
                self::CACHE_SECONDS,
                fn () => $this->gemini->assessRequirements($requirements, $cvText, $cvFile)
            );
        } catch (GeminiException $e) {
            Log::warning('AI requirement match failed; using keyword match', ['error' => $e->getMessage()]);

            return [...$this->keywords->match($jobDescription, $cvText), 'requirements' => [], 'source' => 'keywords'];
        }

        $matched = [];
        $missing = [];
        $detailed = [];
        foreach ($requirements as $i => $requirement) {
            $met = $assessment[$i]['met'] ?? false;
            $met ? $matched[] = $requirement['name'] : $missing[] = $requirement['name'];
            $detailed[] = [...$requirement, 'met' => $met, 'evidence' => $assessment[$i]['evidence'] ?? ''];
        }

        return [
            'score' => (int) round(count($matched) / max(1, count($requirements)) * 100),
            'matched' => $matched,
            'missing' => $missing,
            'required' => array_column($requirements, 'name'),
            'requirements' => $detailed,
            'source' => 'ai',
        ];
    }

    private function key(string $kind, string $content): string
    {
        return 'match:'.self::CACHE_VERSION.':'.$kind.':'.sha1($content);
    }

    /**
     * Local file cache: avoids a database round trip, and results are cheap to recompute.
     */
    private function cache(): Repository
    {
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }
}
