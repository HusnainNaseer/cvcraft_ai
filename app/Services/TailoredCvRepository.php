<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores each tailored CV as a JSON file addressed by an unguessable token.
 *
 * File shape: {"cv": {...}, "job": {...}, "created_at": "..."}.
 * Older files that hold the CV at the top level are still readable.
 */
class TailoredCvRepository
{
    public const TOKEN_PATTERN = '/^[a-zA-Z0-9]{40}$/';

    public function create(array $cv, array $job): string
    {
        File::ensureDirectoryExists($this->directory(), 0755);

        $token = Str::random(40);
        $this->write($token, [
            'cv' => $cv,
            'job' => $job,
            'created_at' => now()->toIso8601String(),
        ]);

        return $token;
    }

    /**
     * @return array{cv: array, job: array}|null
     */
    public function find(string $token): ?array
    {
        if (! $this->isValidToken($token)) {
            return null;
        }

        $path = $this->path($token);
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return null;
        }

        if (! isset($data['cv'])) {
            return ['cv' => $data, 'job' => []];
        }

        return [
            'cv' => is_array($data['cv']) ? $data['cv'] : [],
            'job' => is_array($data['job'] ?? null) ? $data['job'] : [],
        ];
    }

    public function updateCv(string $token, array $cv): void
    {
        $record = $this->find($token);
        if (! $record) {
            throw new RuntimeException('Tailored CV result not found.');
        }

        $this->write($token, [...$record, 'cv' => $cv, 'updated_at' => now()->toIso8601String()]);
    }

    /**
     * Delete results older than the given number of days. Returns how many were removed.
     */
    public function prune(int $days): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (glob($this->directory().DIRECTORY_SEPARATOR.'*.json') ?: [] as $file) {
            if (filemtime($file) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function isValidToken(string $token): bool
    {
        return preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    private function write(string $token, array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($this->path($token), $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Could not save the tailored CV.');
        }
    }

    private function path(string $token): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$token.'.json';
    }

    private function directory(): string
    {
        return storage_path('app/private/tailored-cvs');
    }
}
