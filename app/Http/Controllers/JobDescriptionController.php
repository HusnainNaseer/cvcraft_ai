<?php

namespace App\Http\Controllers;

use App\Services\ApplicationContact;
use App\Services\CvUploadParser;
use App\Services\JobPostingFetcher;
use App\Services\RequirementMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class JobDescriptionController extends Controller
{
    public function fetch(Request $request, JobPostingFetcher $fetcher): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        set_time_limit(180);

        try {
            $job = $fetcher->fetch($validated['url']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            ...$job,
            'application' => ApplicationContact::resolve($job),
        ]);
    }

    /**
     * How well the uploaded CV covers the job's requirements, before tailoring. Works for jobs in any field;
     * when the CV's text cannot be read (many PDFs), the AI reads the file itself.
     */
    public function skills(Request $request, CvUploadParser $parser, RequirementMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'jobDescription' => ['required', 'string', 'min:30', 'max:30000'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx,txt,html,htm', 'max:10240'],
        ]);

        set_time_limit(90);

        $file = $request->file('cv');
        $cvText = $parser->extract($file);
        $cvFile = Str::length($cvText) < 30 ? $parser->aiFilePart($file) : null;
        if (Str::length($cvText) < 30 && ! $cvFile) {
            return response()->json(['message' => 'Could not read this CV. Try a PDF or TXT version.'], 422);
        }

        return response()->json([
            'success' => true,
            ...$matcher->match($validated['jobDescription'], Str::length($cvText) < 30 ? '' : $cvText, $cvFile),
        ]);
    }
}
