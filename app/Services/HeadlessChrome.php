<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs a locally installed Chrome or Edge in headless mode.
 */
class HeadlessChrome
{
    private const CANDIDATE_PATHS = [
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];

    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * Render an HTML document (scripts included) to PDF bytes.
     */
    public function htmlToPdf(string $html): string
    {
        $workDir = $this->makeWorkDir();
        $htmlPath = $workDir.DIRECTORY_SEPARATOR.'document.html';
        $pdfPath = $workDir.DIRECTORY_SEPARATOR.'document.pdf';

        try {
            file_put_contents($htmlPath, $html);

            $this->run($workDir, [
                '--no-pdf-header-footer',
                '--virtual-time-budget=8000',
                '--print-to-pdf='.$pdfPath,
                $this->fileUrl($htmlPath),
            ]);

            $pdf = is_file($pdfPath) ? (string) file_get_contents($pdfPath) : '';
            if (! str_starts_with($pdf, '%PDF')) {
                throw new RuntimeException('The browser did not produce a PDF.');
            }

            return $pdf;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * Load a public URL like a real browser and return the rendered DOM.
     */
    public function dumpDom(string $url): string
    {
        $workDir = $this->makeWorkDir();

        try {
            return $this->run($workDir, ['--virtual-time-budget=10000', '--dump-dom', $url]);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function run(string $workDir, array $arguments): string
    {
        $binary = $this->binary();
        if (! $binary) {
            throw new RuntimeException('Chrome or Edge was not found. Set CHROME_PATH in .env.');
        }

        $process = new Process([
            $binary,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-dev-shm-usage',
            // Docker containers usually lack the kernel features Chrome's sandbox needs.
            ...(config('services.chrome.no_sandbox') ? ['--no-sandbox'] : []),
            '--user-data-dir='.$workDir.DIRECTORY_SEPARATOR.'profile',
            ...$arguments,
        ]);
        $process->setTimeout(60);
        $process->run();

        return $process->getOutput();
    }

    private function binary(): ?string
    {
        $configured = config('services.chrome.path');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach (self::CANDIDATE_PATHS as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function makeWorkDir(): string
    {
        $directory = storage_path('app/headless/'.Str::random(16));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create a temporary browser directory.');
        }

        return $directory;
    }

    private function fileUrl(string $path): string
    {
        return 'file:///'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
