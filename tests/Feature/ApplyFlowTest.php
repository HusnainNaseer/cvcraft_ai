<?php

namespace Tests\Feature;

use App\Http\Controllers\GoogleAuthController;
use App\Services\HeadlessChrome;
use App\Services\TailoredCvRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Upload CV -> job description -> tailor -> download -> apply, with Gemini, Gmail and Chrome faked.
 */
class ApplyFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_CV = "Jane Doe\njane@example.net | +1 555 0100\nPHP and Laravel developer.\n"
        ."Experience\nDeveloper, Widgets Ltd (2022 - 2025)\nBuilt Laravel APIs in PHP for the sales team.\n"
        ."Skills: PHP, Laravel\nLanguages: English, Spanish";

    private const JOB = "Laravel Developer at Acme\n\nRequirements:\n- PHP\n- Laravel\n- Docker\n\nSend your CV to careers@acme.io before Friday.";

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.fallback_api_key' => null,
            'services.gemini.api_keys' => null,
            'services.gemini.models' => 'gemini-flash-latest',
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        $this->mock(HeadlessChrome::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('htmlToPdf')->andReturn('%PDF-1.4 fake');
        });
    }

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Connect Gmail');
    }

    public function test_tailoring_detects_the_email_method_and_keeps_skills_truthful(): void
    {
        $this->fakeGemini(
            ['cv' => $this->sampleCv(), 'reasoning' => 'Reordered skills.'],
            // One verdict per checked text: about, the job title and the job description.
            ['results' => [['id' => 'c1', 'verdict' => 'keep'], ['id' => 'c2', 'verdict' => 'keep'], ['id' => 'c3', 'verdict' => 'keep']]],
        );

        $response = $this->post('/cv/tailor', [
            'jobDescription' => self::JOB,
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', self::SOURCE_CV),
            'tone' => 'concise',
            'job' => ['title' => 'Laravel Developer', 'company' => 'Acme'],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.application.method', 'email')
            ->assertJsonPath('data.application.email', 'careers@acme.io')
            ->assertJsonPath('data.match.missing', ['Docker'])
            ->assertJsonPath('data.factCheck', ['verified' => true, 'removed' => []]);

        $token = $response->json('data.result');
        $record = app(TailoredCvRepository::class)->find($token);

        $this->assertSame('Acme', $record['job']['company']);
        $this->assertNotContains('Job Requirements', array_column($record['cv']['skills'], 'label'));
    }

    public function test_download_returns_a_pdf(): void
    {
        $token = $this->storeResult();

        $this->get('/cv/download?result='.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Jane-Doe-CV.pdf"');
    }

    public function test_prepare_drafts_an_email_for_the_job(): void
    {
        $token = $this->storeResult();
        $this->fakeGemini(['subject' => 'Application for Laravel Developer - Jane Doe', 'body' => 'Dear Hiring Manager, ...']);

        $this->postJson('/application/prepare', ['result' => $token])
            ->assertOk()
            ->assertJsonPath('application.method', 'email')
            ->assertJsonPath('draft.subject', 'Application for Laravel Developer - Jane Doe')
            ->assertJsonPath('sender.gmailConfigured', true)
            ->assertJsonPath('sender.gmailEmail', null);
    }

    public function test_application_email_is_sent_through_gmail_with_the_pdf_attached(): void
    {
        $token = $this->storeResult();
        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'msg-1'])]);

        $this->withSession([GoogleAuthController::SESSION_KEY => [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => time() + 3600,
            'email' => 'jane@gmail.com',
            'name' => 'Jane Doe',
        ]])->postJson('/application/email', [
            'result' => $token,
            'to' => 'careers@acme.io',
            'subject' => 'Application for Laravel Developer - Jane Doe',
            'body' => 'Dear Hiring Manager, please find my CV attached.',
        ])->assertOk()->assertJsonPath('success', true);

        Http::assertSent(function (Request $request) {
            $mime = base64_decode(strtr($request['raw'], '-_', '+/'));

            return $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
                && $request->hasHeader('Authorization', 'Bearer access')
                && str_contains($mime, 'To: careers@acme.io')
                && str_contains($mime, 'filename=Jane-Doe-CV.pdf');
        });
    }

    public function test_disabled_gmail_api_gives_an_actionable_message(): void
    {
        $token = $this->storeResult();
        // Shape of Google's response when the Gmail API is not enabled in the Cloud project.
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => [
            'code' => 403,
            'message' => 'Gmail API has not been used in project 123 before or it is disabled.',
            'status' => 'PERMISSION_DENIED',
            'errors' => [['reason' => 'accessNotConfigured']],
            'details' => [['@type' => 'type.googleapis.com/google.rpc.ErrorInfo', 'reason' => 'SERVICE_DISABLED']],
        ]], 403)]);

        $this->withSession([GoogleAuthController::SESSION_KEY => [
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'expires_at' => time() + 3600,
            'email' => 'jane@gmail.com',
            'name' => 'Jane Doe',
        ]])->postJson('/application/email', [
            'result' => $token,
            'to' => 'careers@acme.io',
            'subject' => 'Application for Laravel Developer - Jane Doe',
            'body' => 'Dear Hiring Manager, please find my CV attached.',
        ])->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'enable the Gmail API')
                && ! str_contains($message, 'project 123'));
    }

    public function test_tailoring_removes_claims_that_are_not_in_the_original_cv(): void
    {
        $invented = $this->sampleCv();
        $invented['about'] = 'PHP and Laravel developer who led a team of 8 engineers on SaaS platforms.';
        $invented['experience'][0] += ['business_sector' => 'Software Development', 'department' => 'Engineering'];
        $invented['experience'][] = ['title' => 'Tech Lead', 'company' => 'Google', 'period' => '2019 - 2021'];
        $invented['skills'][0]['items'][] = 'Docker';
        $invented['languages'] = ['mother' => 'Spanish', 'other' => [['name' => 'English', 'listening' => 'C2']]];

        $this->fakeGemini(
            ['cv' => $invented, 'reasoning' => 'Tailored.'],
            ['results' => [
                ['id' => 'c1', 'verdict' => 'remove', 'reason' => 'leadership, team size and SaaS are not in the CV'],
                ['id' => 'c2', 'verdict' => 'keep'],
                ['id' => 'c3', 'verdict' => 'keep'],
            ]],
        );

        $response = $this->post('/cv/tailor', [
            'jobDescription' => self::JOB,
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', self::SOURCE_CV),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertTrue($response->json('data.factCheck.verified'));
        $this->assertNotEmpty($response->json('data.factCheck.removed'));

        $record = app(TailoredCvRepository::class)->find($response->json('data.result'));
        $cv = $record['cv'];
        $this->assertSame(self::SOURCE_CV, $record['source']);
        $this->assertSame('', $cv['about']);
        $this->assertSame(['Widgets Ltd'], array_column($cv['experience'], 'company'));
        $this->assertSame('', $cv['experience'][0]['business_sector']);
        $this->assertSame('', $cv['experience'][0]['department']);
        $this->assertSame(['PHP', 'Laravel'], $cv['skills'][0]['items']);
        $this->assertSame('', $cv['languages']['mother']);
        $this->assertSame('', $cv['languages']['other'][0]['listening']);
        $this->assertContains('Docker', $response->json('data.match.missing'), 'the gap is shown to the user, not hidden');
    }

    public function test_code_checks_still_apply_when_the_ai_fact_checker_fails(): void
    {
        $invented = $this->sampleCv();
        $invented['experience'][0]['business_sector'] = 'Fintech';

        $this->fakeGemini(['cv' => $invented, 'reasoning' => 'Tailored.'], 503);

        $response = $this->post('/cv/tailor', [
            'jobDescription' => self::JOB,
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', self::SOURCE_CV),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertFalse($response->json('data.factCheck.verified'));
        $cv = app(TailoredCvRepository::class)->find($response->json('data.result'))['cv'];
        $this->assertSame('', $cv['experience'][0]['business_sector']);
    }

    public function test_application_email_drops_paragraphs_the_cv_does_not_support(): void
    {
        $token = $this->storeResult();
        $truthful = 'At Widgets Ltd I built Laravel APIs in PHP for the sales team, which matches your need for a Laravel developer.';
        $invented = 'I have also architected scalable SaaS platforms and led a team of 6 engineers to deliver them on time.';

        $this->fakeGemini(
            ['subject' => 'Application for Laravel Developer - Jane Doe', 'body' => "Dear Hiring Manager,\n\n{$truthful}\n\n{$invented}\n\nKind regards,\nJane Doe"],
            ['results' => [['id' => 'c1', 'verdict' => 'keep'], ['id' => 'c2', 'verdict' => 'remove']]],
        );

        $body = $this->postJson('/application/prepare', ['result' => $token])->assertOk()->json('draft.body');

        $this->assertStringContainsString($truthful, $body);
        $this->assertStringNotContainsString('SaaS', $body);
        $this->assertStringContainsString('Kind regards', $body);
    }

    public function test_unverifiable_email_falls_back_to_the_safe_template(): void
    {
        $token = $this->storeResult();
        $this->fakeGemini(
            ['subject' => 'Application', 'body' => "Dear Hiring Manager,\n\nI led the migration of five enterprise SaaS products to Kubernetes.\n\nKind regards,\nJane Doe"],
            503,
        );

        $body = $this->postJson('/application/prepare', ['result' => $token])->assertOk()->json('draft.body');

        $this->assertStringNotContainsString('Kubernetes', $body);
        $this->assertStringContainsString('Please find my CV attached', $body);
    }

    public function test_email_cannot_be_sent_without_a_mail_account(): void
    {
        $token = $this->storeResult();

        $this->postJson('/application/email', [
            'result' => $token,
            'to' => 'careers@acme.io',
            'subject' => 'Application',
            'body' => 'Dear Hiring Manager, please find my CV attached.',
        ])->assertStatus(422)->assertJsonPath('message', 'Connect your Gmail account to send applications.');

        Http::assertNothingSent();
    }

    public function test_job_fetch_rejects_private_addresses(): void
    {
        $this->postJson('/job-description/fetch', ['url' => 'http://127.0.0.1/admin'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please use a public http or https job URL.');
    }

    /**
     * Fake Gemini answering successive calls in order (e.g. tailoring, then the fact check).
     * Pass an int instead of an array to make that call fail with that HTTP status.
     */
    private function fakeGemini(array|int ...$answers): void
    {
        $sequence = Http::sequence();
        foreach ($answers as $answer) {
            is_int($answer)
                ? $sequence->push(['error' => ['message' => 'unavailable']], $answer)
                : $sequence->push(['candidates' => [['content' => ['parts' => [['text' => json_encode($answer)]]]]]]);
        }

        Http::fake(['generativelanguage.googleapis.com/*' => $sequence]);
    }

    private function storeResult(): string
    {
        return app(TailoredCvRepository::class)->create($this->sampleCv(), [
            'title' => 'Laravel Developer',
            'company' => 'Acme',
            'emails' => ['careers@acme.io'],
            'description' => self::JOB,
        ], self::SOURCE_CV);
    }

    private function sampleCv(): array
    {
        return [
            'personal' => ['name' => 'Jane Doe', 'email' => 'jane@example.net', 'phone' => '+1 555 0100'],
            'about' => 'PHP and Laravel developer.',
            'experience' => [['title' => 'Developer', 'company' => 'Widgets Ltd', 'period' => '2022 - 2025', 'description' => 'Built Laravel APIs in PHP for the sales team.']],
            'skills' => [['label' => 'Technical', 'items' => ['PHP', 'Laravel']]],
        ];
    }
}
