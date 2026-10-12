<?php

namespace Tests\Feature;

use App\Http\Controllers\GoogleAuthController;
use App\Services\HeadlessChrome;
use App\Services\TailoredCvRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Upload CV -> job description -> tailor -> download -> apply, with Gemini, Gmail and Chrome faked.
 */
class ApplyFlowTest extends TestCase
{
    private const JOB = "Laravel Developer at Acme\n\nRequirements:\n- PHP\n- Laravel\n- Docker\n\nSend your CV to careers@acme.io before Friday.";

    private array $createdResults = [];

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

    protected function tearDown(): void
    {
        foreach ($this->createdResults as $token) {
            @unlink(storage_path('app/private/tailored-cvs/'.$token.'.json'));
        }

        parent::tearDown();
    }

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Connect Gmail');
    }

    public function test_tailoring_detects_the_email_method_and_keeps_skills_truthful(): void
    {
        $this->fakeGemini(['cv' => $this->sampleCv(), 'reasoning' => 'Reordered skills.']);

        $response = $this->post('/cv/tailor', [
            'jobDescription' => self::JOB,
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', str_repeat('Jane Doe, PHP and Laravel developer. ', 5)),
            'tone' => 'concise',
            'job' => ['title' => 'Laravel Developer', 'company' => 'Acme'],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.application.method', 'email')
            ->assertJsonPath('data.application.email', 'careers@acme.io')
            ->assertJsonPath('data.match.missing', ['Docker']);

        $token = $this->createdResults[] = $response->json('data.result');
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

    private function fakeGemini(array $answer): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($answer)]]]]],
            ]),
        ]);
    }

    private function storeResult(): string
    {
        return $this->createdResults[] = app(TailoredCvRepository::class)->create($this->sampleCv(), [
            'title' => 'Laravel Developer',
            'company' => 'Acme',
            'emails' => ['careers@acme.io'],
            'description' => self::JOB,
        ]);
    }

    private function sampleCv(): array
    {
        return [
            'personal' => ['name' => 'Jane Doe', 'email' => 'jane@example.net', 'phone' => '+1 555 0100'],
            'about' => 'PHP and Laravel developer.',
            'experience' => [['title' => 'Developer', 'company' => 'Widgets Ltd', 'period' => '2022 - 2025', 'description' => 'Built Laravel APIs in PHP.']],
            'skills' => [['label' => 'Technical', 'items' => ['PHP', 'Laravel']]],
        ];
    }
}
