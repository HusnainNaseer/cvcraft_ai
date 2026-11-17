<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class CvUploadParser
{
    /** File types the AI can read directly. */
    private const AI_READABLE_TYPES = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
    ];

    /**
     * The uploaded file in the form the AI accepts, or null for types it cannot read (Word files).
     *
     * @return array{mime_type: string, data: string, name: string}|null
     */
    public function aiFilePart(UploadedFile $file): ?array
    {
        $mimeType = self::AI_READABLE_TYPES[strtolower($file->getClientOriginalExtension())] ?? null;
        $bytes = $mimeType ? $file->get() : null;

        return $bytes ? ['mime_type' => $mimeType, 'data' => base64_encode($bytes), 'name' => $file->getClientOriginalName()] : null;
    }

    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            return '';
        }

        $text = match ($extension) {
            'html', 'htm' => $this->extractHtml((string) file_get_contents($path)),
            'txt' => (string) file_get_contents($path),
            'docx' => $this->extractDocx($path),
            'pdf' => $this->extractPdf($path),
            'doc' => $this->extractReadableBinaryText($path),
            default => '',
        };

        $text = $this->cleanText($text);

        // PDFs that store text through embedded font maps (most exported from Word, Google Docs, Canva or a
        // browser) come out of the simple PDF reader as symbol soup. Unreadable text is worse than none: the AI
        // reads the original file instead and returns a transcript that is used for the fact checks.
        return in_array($extension, ['pdf', 'doc'], true) && ! $this->looksLikeText($text) ? '' : $text;
    }

    /**
     * True when most tokens are real words rather than PDF glyph codes.
     */
    public function looksLikeText(string $text): bool
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) < 20) {
            return count($tokens) > 0;
        }

        $words = 0;
        foreach ($tokens as $token) {
            $token = trim($token, '.,;:()[]"\'!?|•–—-');
            // A word: two or more letters, containing a vowel (Latin scripts) or any non-Latin letter.
            if (preg_match('/^\p{L}[\p{L}\'’.-]+$/u', $token) && preg_match('/[aeiouyAEIOUY]|[^\x00-\x7F]/u', $token)) {
                $words++;
            }
        }

        return $words / count($tokens) >= 0.45;
    }

    private function extractHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|section|article|li|tr|h[1-6])>/i', "\n", $html) ?? $html;

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function extractDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return '';
        }

        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:tr>/', "\n", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractPdf(string $path): string
    {
        $content = (string) file_get_contents($path);
        if ($content === '') {
            return '';
        }

        $text = $this->extractPdfStreams($content);

        if (Str::length($this->cleanText($text)) < 80) {
            $text .= "\n".$this->extractReadableBinaryText($path);
        }

        return $text;
    }

    private function extractPdfStreams(string $content): string
    {
        preg_match_all('/(<<.*?>>)\s*stream\r?\n(.*?)\r?\nendstream/s', $content, $matches, PREG_SET_ORDER);

        $chunks = [];
        foreach ($matches as $match) {
            $dictionary = $match[1] ?? '';
            $stream = $match[2] ?? '';

            if (str_contains($dictionary, '/FlateDecode')) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzdecode($stream);
                }
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if (is_string($decoded)) {
                    $stream = $decoded;
                }
            }

            $chunk = $this->extractPdfTextOperators($stream);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return implode("\n", $chunks);
    }

    private function extractPdfTextOperators(string $stream): string
    {
        $parts = [];

        preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $stream, $literalMatches);
        foreach ($literalMatches[0] ?? [] as $value) {
            $parts[] = $this->decodePdfLiteral(substr($value, 1, -1));
        }

        preg_match_all('/<([0-9A-Fa-f\s]{4,})>/', $stream, $hexMatches);
        foreach ($hexMatches[1] ?? [] as $value) {
            $decoded = $this->decodePdfHex($value);
            if ($decoded !== '') {
                $parts[] = $decoded;
            }
        }

        return implode(' ', array_filter($parts));
    }

    private function decodePdfLiteral(string $value): string
    {
        $value = preg_replace('/\\\\([nrtbf])/', ' ', $value) ?? $value;
        $value = preg_replace('/\\\\([()\\\\])/', '$1', $value) ?? $value;
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($match) => chr(octdec($match[1])), $value) ?? $value;

        return $value;
    }

    private function decodePdfHex(string $value): string
    {
        $hex = preg_replace('/\s+/', '', $value) ?? '';
        if ($hex === '') {
            return '';
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if (! is_string($binary)) {
            return '';
        }

        if (str_starts_with($binary, "\xFE\xFF")) {
            return mb_convert_encoding(substr($binary, 2), 'UTF-8', 'UTF-16BE');
        }

        return $binary;
    }

    private function extractReadableBinaryText(string $path): string
    {
        $content = (string) file_get_contents($path);
        preg_match_all('/[A-Za-z0-9@%+.,;:\/#()&_\-\s]{4,}/', $content, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    private function cleanText(string $text): string
    {
        // PDF/DOC extraction can yield raw bytes; drop anything that is not valid UTF-8.
        $text = mb_scrub(str_replace("\0", ' ', $text), 'UTF-8');
        $text = preg_replace('/[^\P{C}\r\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        return Str::limit($text, 20000, '');
    }
}
