<?php

namespace App\Services;

use App\Exceptions\GeminiException;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Downloads a job advert from a URL and turns it into structured job data.
 */
class JobPostingFetcher
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    private const MAX_REDIRECTS = 5;

    /** Below this many characters a description is treated as "not found". */
    private const MIN_DESCRIPTION = 200;

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly HeadlessChrome $chrome,
    ) {
    }

    /**
     * @return array{title: string, company: string, location: string, description: string, url: string, emails: string[], applyUrl: string}
     *
     * @throws InvalidArgumentException when the URL is not a public web address
     * @throws RuntimeException when no job description could be read
     */
    public function fetch(string $url): array
    {
        $this->assertPublicUrl($url);

        $job = $this->fetchLinkedInJob($url) ?? $this->fetchGenericJob($url);
        $job['url'] = $url;
        $job['emails'] = ApplicationContact::emails($job['description'].' '.implode(' ', $job['emails'] ?? []));

        return $job;
    }

    /**
     * LinkedIn serves public job adverts through its guest endpoint without login.
     */
    private function fetchLinkedInJob(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! Str::endsWith($host, 'linkedin.com')) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $jobId = $query['currentJobId'] ?? null;
        if (! $jobId && preg_match('~/jobs/view/(?:[^/?#]*?-)?(\d{6,})~', $url, $match)) {
            $jobId = $match[1];
        }
        if (! is_string($jobId) || ! ctype_digit($jobId)) {
            return null;
        }

        $html = $this->download('https://www.linkedin.com/jobs-guest/jobs/api/jobPosting/'.$jobId);
        if ($html === null) {
            return null;
        }

        $xpath = new DOMXPath($this->loadHtml($html));
        $text = fn (string $query) => $this->cleanText($xpath->evaluate('string('.$query.')'));
        $descriptionNode = $xpath->query('//div[contains(@class,"show-more-less-html__markup")]')->item(0);
        $description = $descriptionNode ? $this->htmlToText($descriptionNode->ownerDocument->saveHTML($descriptionNode)) : '';

        if (Str::length($description) < self::MIN_DESCRIPTION) {
            return null;
        }

        return [
            'title' => $text('//h2[contains(@class,"top-card-layout__title")]'),
            'company' => $text('//a[contains(@class,"topcard__org-name-link")]'),
            'location' => $text('//span[contains(@class,"topcard__flavor--bullet")]'),
            'description' => $description,
            'emails' => [],
            'applyUrl' => '',
        ];
    }

    private function fetchGenericJob(string $url): array
    {
        $html = $this->download($url);

        $job = $html !== null ? $this->parseStructuredJobPosting($html) : null;
        if ($job) {
            return $job;
        }

        // Plain HTTP was blocked or the page builds its content with JavaScript: load it in a real browser.
        if ($html === null || Str::length($this->pageText($html)) < self::MIN_DESCRIPTION * 2) {
            $html = $this->renderInBrowser($url) ?? $html;
            $job = $html !== null ? $this->parseStructuredJobPosting($html) : null;
            if ($job) {
                return $job;
            }
        }

        if ($html === null) {
            throw new RuntimeException('This job site blocked the request. Please paste the job description manually.');
        }

        return $this->extractWithGemini($html, $url) ?? $this->extractFromMetaTags($html);
    }

    private function extractWithGemini(string $html, string $url): ?array
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        try {
            $job = $this->gemini->extractJobPosting($this->pageText($html), $url);
        } catch (GeminiException $e) {
            Log::warning('Gemini job extraction failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $job['is_job_posting'] || Str::length($job['description']) < self::MIN_DESCRIPTION) {
            throw new RuntimeException('No job description was found on this page. Please paste it manually.');
        }

        return [
            'title' => $job['title'],
            'company' => $job['company'],
            'location' => $job['location'],
            'description' => $job['description'],
            'emails' => array_filter([$job['application_email']]),
            'applyUrl' => $this->absoluteHttpUrl($job['apply_url'], $url),
        ];
    }

    private function extractFromMetaTags(string $html): array
    {
        $xpath = new DOMXPath($this->loadHtml($html));
        $first = function (array $queries) use ($xpath): string {
            foreach ($queries as $query) {
                $value = $this->cleanText($xpath->evaluate('string('.$query.')'));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $description = $first(['//meta[@property="og:description"]/@content', '//meta[@name="description"]/@content']);
        if (Str::length($description) < self::MIN_DESCRIPTION) {
            $description = Str::limit($this->pageText($html), 6000, '');
        }

        if (Str::length($description) < self::MIN_DESCRIPTION) {
            throw new RuntimeException('No readable job description was found on this page. Please paste it manually.');
        }

        return [
            'title' => $first(['//meta[@property="og:title"]/@content', '//title', '//h1']),
            'company' => '',
            'location' => '',
            'description' => $description,
            'emails' => [],
            'applyUrl' => '',
        ];
    }

    private function parseStructuredJobPosting(string $html): ?array
    {
        $xpath = new DOMXPath($this->loadHtml($html));

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $job = $this->findJobPosting(json_decode($script->textContent, true));
            if (! $job) {
                continue;
            }

            $description = $this->htmlToText((string) ($job['description'] ?? ''));
            if (Str::length($description) < self::MIN_DESCRIPTION) {
                continue;
            }

            $location = data_get($job, 'jobLocation.address') ?? data_get($job, 'jobLocation.0.address') ?? [];

            return [
                'title' => $this->cleanText($job['title'] ?? ''),
                'company' => $this->cleanText(data_get($job, 'hiringOrganization.name', '')),
                'location' => $this->cleanText(implode(', ', array_filter([
                    data_get($location, 'addressLocality'),
                    data_get($location, 'addressRegion'),
                    data_get($location, 'addressCountry.name', data_get($location, 'addressCountry')),
                ], 'is_string'))),
                'description' => $description,
                'emails' => array_filter([data_get($job, 'applicationContact.email')], 'is_string'),
                'applyUrl' => is_string($job['url'] ?? null) && ($job['directApply'] ?? false) ? $job['url'] : '',
            ];
        }

        return null;
    }

    private function findJobPosting(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $type = $payload['@type'] ?? null;
        if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
            return $payload;
        }

        $children = [
            ...(array) ($payload['@graph'] ?? []),
            ...(array) ($payload['itemListElement'] ?? []),
            ...(array_is_list($payload) ? $payload : []),
        ];

        foreach ($children as $item) {
            $found = $this->findJobPosting(is_array($item) ? ($item['item'] ?? $item) : null);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * GET a URL, re-validating every redirect hop so it cannot be bounced to a private address.
     */
    private function download(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(8)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->get($url);
            } catch (\Throwable $e) {
                Log::info('Job page download failed', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }

            if ($response->redirect()) {
                $url = $this->absoluteHttpUrl((string) $response->header('Location'), $url);
                if ($url === '') {
                    return null;
                }
                $this->assertPublicUrl($url);

                continue;
            }

            return $response->successful() ? $response->body() : null;
        }

        return null;
    }

    private function renderInBrowser(string $url): ?string
    {
        if (! $this->chrome->isAvailable()) {
            return null;
        }

        try {
            $html = $this->chrome->dumpDom($url);
        } catch (\Throwable $e) {
            Log::info('Headless job page render failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        return Str::length($html) > 500 ? $html : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = trim(strtolower($parts['host'] ?? ''), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Please use a public http or https job URL.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('This job URL could not be found. Check the link and try again.');
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Please use a public http or https job URL.');
            }
        }
    }

    private function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }

    private function absoluteHttpUrl(string $candidate, string $base): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        if (str_starts_with($candidate, '//')) {
            $candidate = parse_url($base, PHP_URL_SCHEME).':'.$candidate;
        } elseif (str_starts_with($candidate, '/')) {
            $candidate = parse_url($base, PHP_URL_SCHEME).'://'.parse_url($base, PHP_URL_HOST).$candidate;
        }

        return preg_match('~^https?://~i', $candidate) === 1 ? $candidate : '';
    }

    /**
     * Visible page text with scripts, styles and page chrome removed.
     */
    private function pageText(string $html): string
    {
        $html = preg_replace('~<(script|style|noscript|svg|nav|footer|header)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;

        return $this->htmlToText($html);
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace('~<li\b[^>]*>~i', "\n- ", $html) ?? $html;
        $html = preg_replace('~</(p|div|section|article|li|ul|ol|tr|h[1-6])>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\R */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function cleanText(mixed $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function loadHtml(string $html): DOMDocument
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        return $document;
    }
}
