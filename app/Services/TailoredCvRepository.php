<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores each tailored CV in the `tailored_cvs` table, addressed by an unguessable token.
 */
class TailoredCvRepository
{
    public const TOKEN_PATTERN = '/^[a-zA-Z0-9]{40}$/';

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  string  $source  text of the original uploaded CV, used to fact-check later AI output
     */
    public function create(array $cv, array $job, string $source = ''): string
    {
        $token = Str::random(40);
        $now = now();

        $this->table()->insert([
            'token' => $token,
            'cv' => $this->encode($cv),
            'job' => $this->encode($job),
            'source' => $source !== '' ? $source : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $token;
    }

    /**
     * @return array{cv: array, job: array, source: string}|null
     */
    public function find(string $token): ?array
    {
        if (! $this->isValidToken($token)) {
            return null;
        }

        $row = $this->table()->where('token', $token)->first(['cv', 'job', 'source']);
        if (! $row) {
            return null;
        }

        $cv = json_decode((string) $row->cv, true);
        $job = json_decode((string) $row->job, true);

        return [
            'cv' => is_array($cv) ? $cv : [],
            'job' => is_array($job) ? $job : [],
            'source' => (string) ($row->source ?? ''),
        ];
    }

    public function updateCv(string $token, array $cv): void
    {
        $updated = $this->isValidToken($token)
            ? $this->table()->where('token', $token)->update([
                'cv' => $this->encode($cv),
                'updated_at' => now(),
            ])
            : 0;

        if ($updated === 0) {
            throw new RuntimeException('Tailored CV result not found.');
        }
    }

    /**
     * Delete results not updated in the given number of days. Returns how many were removed.
     */
    public function prune(int $days): int
    {
        return $this->table()->where('updated_at', '<', now()->subDays($days))->delete();
    }

    public function isValidToken(string $token): bool
    {
        return preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    private function encode(array $data): string
    {
        $encoded = json_encode($data, self::JSON_FLAGS);
        if ($encoded === false) {
            throw new RuntimeException('Could not save the tailored CV.');
        }

        return $encoded;
    }

    private function table(): Builder
    {
        return DB::table('tailored_cvs');
    }
}
