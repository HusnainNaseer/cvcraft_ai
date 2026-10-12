<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns CV data into the final HTML page or PDF using public/cv_template.html.
 */
class CvDocumentRenderer
{
    public function __construct(private readonly HeadlessChrome $chrome)
    {
    }

    public function html(array $cv): string
    {
        $templatePath = public_path('cv_template.html');
        if (! is_file($templatePath)) {
            throw new RuntimeException('CV template not found.');
        }

        $json = json_encode(
            $cv,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if (! is_string($json)) {
            throw new RuntimeException('CV data could not be encoded.');
        }

        $embeddedData = '<script type="application/json" id="cvData">'.$json.'</script>';

        return str_replace('</body>', $embeddedData."\n</body>", (string) file_get_contents($templatePath));
    }

    public function pdf(array $cv): string
    {
        return $this->chrome->htmlToPdf($this->html($cv));
    }

    public function pdfAvailable(): bool
    {
        return $this->chrome->isAvailable();
    }

    /**
     * File name such as "Jane-Doe-CV.pdf".
     */
    public function fileName(array $cv, string $extension): string
    {
        $name = Str::slug((string) data_get($cv, 'personal.name', ''));

        return ($name !== '' ? Str::title($name) : 'Tailored').'-CV.'.$extension;
    }
}
