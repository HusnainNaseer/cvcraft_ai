<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiException;
use App\Services\ApplicationContact;
use App\Services\ApplicationMailer;
use App\Services\CvDocumentRenderer;
use App\Services\GeminiService;
use App\Services\GmailClient;
use App\Services\TailoredCvRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Applies for the job with the tailored CV, using the application method the job asks for.
 */
class ApplicationController extends Controller
{
    public function __construct(private readonly TailoredCvRepository $results)
    {
    }

    /**
     * Work out how to apply and draft the application email.
     */
    public function prepare(Request $request, GeminiService $gemini, GmailClient $gmail, ApplicationMailer $mailer, CvDocumentRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
            'tone' => ['nullable', 'string', 'in:professional,concise,detailed'],
        ]);

        // Drafting plus fact-checking the email takes two Gemini calls.
        set_time_limit(150);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV not found. Please generate it again.'], 404);
        }

        ['cv' => $cv, 'job' => $job, 'source' => $source] = $record;

        try {
            $draft = $gemini->draftApplicationEmail($cv, $job, $validated['tone'] ?? 'professional', $source);
        } catch (GeminiException $e) {
            Log::warning('Application email draft fell back to template', ['error' => $e->getMessage()]);
            $draft = $this->fallbackDraft($cv, $job);
        }

        $gmailToken = $request->session()->get(GoogleAuthController::SESSION_KEY);

        return response()->json([
            'application' => ApplicationContact::resolve($job),
            'job' => ['title' => $job['title'] ?? '', 'company' => $job['company'] ?? ''],
            'draft' => $draft,
            'attachmentName' => $renderer->fileName($cv, $renderer->pdfAvailable() ? 'pdf' : 'html'),
            'sender' => [
                'gmailConfigured' => $gmail->isConfigured(),
                'gmailEmail' => is_array($gmailToken) ? $gmailToken['email'] : null,
                'smtpAvailable' => $mailer->smtpAvailable(),
            ],
        ]);
    }

    /**
     * Email the application with the tailored CV attached.
     */
    public function sendEmail(Request $request, ApplicationMailer $mailer, CvDocumentRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'result' => ['required', 'string', 'regex:'.TailoredCvRepository::TOKEN_PATTERN],
            'to' => ['required', 'email:rfc', 'max:254'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        set_time_limit(120);

        $record = $this->results->find($validated['result']);
        if (! $record) {
            return response()->json(['message' => 'Tailored CV not found. Please generate it again.'], 404);
        }

        $cv = $record['cv'];

        try {
            $attachment = [
                'name' => $renderer->fileName($cv, 'pdf'),
                'data' => $renderer->pdf($cv),
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable $e) {
            Log::error('PDF rendering failed, attaching HTML CV', ['error' => $e->getMessage()]);
            $attachment = [
                'name' => $renderer->fileName($cv, 'html'),
                'data' => $renderer->html($cv),
                'mime' => 'text/html',
            ];
        }

        $gmailToken = $request->session()->get(GoogleAuthController::SESSION_KEY);
        $candidateEmail = (string) data_get($cv, 'personal.email', '');

        try {
            $sent = $mailer->send(is_array($gmailToken) ? $gmailToken : null, [
                'to' => $validated['to'],
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'replyTo' => filter_var($candidateEmail, FILTER_VALIDATE_EMAIL) ? $candidateEmail : null,
                'senderName' => (string) data_get($cv, 'personal.name', ''),
            ], $attachment);
        } catch (\Throwable $e) {
            Log::error('Application email failed', ['result' => $validated['result'], 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($sent['token']) {
            $request->session()->put(GoogleAuthController::SESSION_KEY, $sent['token']);
        }

        Log::info('Application email sent', ['result' => $validated['result'], 'to' => $validated['to'], 'via' => $sent['via']]);

        return response()->json([
            'success' => true,
            'message' => 'Application sent to '.$validated['to'].' from '.$sent['from'].'.',
        ]);
    }

    private function fallbackDraft(array $cv, array $job): array
    {
        $name = (string) data_get($cv, 'personal.name', '');
        $title = ($job['title'] ?? '') ?: 'the advertised position';
        $company = ($job['company'] ?? '') ? ' at '.$job['company'] : '';
        $contact = implode("\n", array_filter([$name, data_get($cv, 'personal.phone'), data_get($cv, 'personal.email')]));

        return [
            'subject' => trim('Application for '.$title.($name ? ' - '.$name : '')),
            'body' => "Dear Hiring Manager,\n\nI am writing to apply for {$title}{$company}. "
                ."My experience and skills match the requirements of the role, and I would welcome the chance to contribute to your team.\n\n"
                ."Please find my CV attached. I look forward to hearing from you.\n\nKind regards,\n{$contact}",
        ];
    }
}
