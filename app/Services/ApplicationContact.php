<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Works out how a candidate should apply for a job.
 */
class ApplicationContact
{
    public const METHOD_EMAIL = 'email';

    public const METHOD_LINKEDIN = 'linkedin';

    public const METHOD_WEBSITE = 'website';

    public const METHOD_MANUAL = 'manual';

    private const IGNORED_EMAIL_PATTERN = '/^(?:no-?reply|do-?not-?reply|mailer-daemon|postmaster)@|@(?:example\.(?:com|org)|sentry\.|wixpress\.com)|\.(?:png|jpe?g|gif|webp|svg)$/i';

    /**
     * Email addresses found in free text, most likely application addresses first.
     *
     * @return string[]
     */
    public static function emails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        $emails = array_values(array_unique(array_filter(
            array_map(fn ($email) => strtolower(trim($email, '.')), $matches[0]),
            fn ($email) => preg_match(self::IGNORED_EMAIL_PATTERN, $email) !== 1
                && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        )));

        usort($emails, fn ($a, $b) => self::emailRank($a) <=> self::emailRank($b));

        return $emails;
    }

    /**
     * @return array{method: string, email: string, emails: string[], applyUrl: string}
     */
    public static function resolve(array $job): array
    {
        $emails = array_values(array_filter((array) ($job['emails'] ?? []), 'is_string'));
        $jobUrl = (string) ($job['url'] ?? '');
        $applyUrl = (string) ($job['applyUrl'] ?? '');

        $method = match (true) {
            $emails !== [] => self::METHOD_EMAIL,
            $applyUrl !== '' => self::METHOD_WEBSITE,
            Str::contains(strtolower($jobUrl), 'linkedin.com') => self::METHOD_LINKEDIN,
            $jobUrl !== '' => self::METHOD_WEBSITE,
            default => self::METHOD_MANUAL,
        };

        return [
            'method' => $method,
            'email' => $emails[0] ?? '',
            'emails' => $emails,
            'applyUrl' => $applyUrl ?: $jobUrl,
        ];
    }

    private static function emailRank(string $email): int
    {
        return preg_match('/^(?:careers?|jobs?|hr|hiring|recruit\w*|talent|apply|cv|resumes?)[@.]/', $email) === 1 ? 0 : 1;
    }
}
