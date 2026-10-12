<?php

namespace App\Http\Controllers;

use App\Services\CvDocumentRenderer;
use App\Services\TailoredCvRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    public function __construct(
        private readonly TailoredCvRepository $results,
        private readonly CvDocumentRenderer $renderer,
    ) {
    }

    /**
     * Download the tailored CV as PDF (default) or HTML (?format=html).
     */
    public function cv(Request $request): Response
    {
        $cv = $this->cvFromRequest($request);

        if ($request->query('format') !== 'html') {
            try {
                return response($this->renderer->pdf($cv), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$this->renderer->fileName($cv, 'pdf').'"',
                ]);
            } catch (\Throwable $e) {
                Log::error('PDF rendering failed, serving HTML instead', ['error' => $e->getMessage()]);
            }
        }

        return response($this->renderer->html($cv), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->renderer->fileName($cv, 'html').'"',
        ]);
    }

    /**
     * Public, view-only page for the tailored CV (also used by the preview iframe).
     */
    public function share(Request $request): Response
    {
        return response($this->renderer->html($this->cvFromRequest($request)), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function cvFromRequest(Request $request): array
    {
        $record = $this->results->find((string) $request->query('result', ''));
        abort_unless($record !== null, 404, 'Tailored CV not found or expired.');

        return $record['cv'];
    }
}
