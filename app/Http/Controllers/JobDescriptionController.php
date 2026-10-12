<?php

namespace App\Http\Controllers;

use App\Services\ApplicationContact;
use App\Services\CvUploadParser;
use App\Services\JobPostingFetcher;
use App\Services\SkillMatcher;
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

    public function skills(Request $request, CvUploadParser $parser, SkillMatcher $matcher): JsonResponse
    {
        $validated = $request->validate([
            'jobDescription' => ['required', 'string', 'min:30', 'max:30000'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx,txt,html,htm', 'max:10240'],
        ]);

        $cvText = $parser->extract($request->file('cv'));
        if (Str::length($cvText) < 30) {
            return response()->json(['message' => 'Could not read enough CV text to compare skills.'], 422);
        }

        return response()->json([
            'success' => true,
            ...$matcher->match($validated['jobDescription'], $cvText),
        ]);
    }
}
