<?php

namespace Tests\Feature;

use App\Services\TailoredCvRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The requirement match works for jobs in any field, uses the same requirement list before and after
 * tailoring, reads unreadable PDFs through the AI, and falls back to keywords without the AI.
 */
class RequirementMatchTest extends TestCase
{
    use RefreshDatabase;

    private const CALL_CENTRE_JOB = "Customer Sales Representative (night shift)\nWe need agents with good spoken English to handle inbound and outbound calls for a US campaign. Sales experience preferred.";

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.fallback_api_key' => null,
            'services.gemini.api_keys' => null,
            'services.gemini.models' => 'gemini-test',
        ]);
    }

    public function test_a_non_technical_job_gets_a_real_score_and_results_are_cached(): void
    {
        $token = app(TailoredCvRepository::class)->create([
            'personal' => ['name' => 'Bilal Ahmed'],
            'about' => 'Customer service agent handling 80 inbound calls per night shift. English (C1).',
        ], ['description' => self::CALL_CENTRE_JOB]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->answer(['requirements' => [
                ['name' => 'Fluent spoken English', 'type' => 'language', 'importance' => 'required'],
                ['name' => 'Inbound call handling', 'type' => 'experience', 'importance' => 'required'],
                ['name' => 'Night shift availability', 'type' => 'availability', 'importance' => 'required'],
                ['name' => 'Sales experience', 'type' => 'experience', 'importance' => 'preferred'],
            ]]))
            ->push($this->answer(['results' => [
                ['id' => 'r1', 'met' => true, 'evidence' => 'English (C1)'],
                ['id' => 'r2', 'met' => true, 'evidence' => '80 inbound calls'],
                ['id' => 'r3', 'met' => true, 'evidence' => 'per night shift'],
                ['id' => 'r4', 'met' => false, 'evidence' => ''],
            ]]))]);

        $this->postJson('/cv/match', ['result' => $token])
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('score', 75)
            ->assertJsonPath('matched', ['Fluent spoken English', 'Inbound call handling', 'Night shift availability'])
            ->assertJsonPath('missing', ['Sales experience']);

        // Same job and CV again: answered from the cache, no new AI calls.
        $this->postJson('/cv/match', ['result' => $token])->assertOk()->assertJsonPath('score', 75);
        Http::assertSentCount(2);
    }

    public function test_an_unreadable_pdf_is_sent_to_the_ai_as_a_file(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->answer(['requirements' => [['name' => 'Fluent spoken English']]]))
            ->push($this->answer(['results' => [['id' => 'r1', 'met' => true, 'evidence' => 'C1']]]))]);

        // Bytes our own PDF reader cannot turn into words.
        $pdf = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n".str_repeat('fi fi ? Z < Z v ^ v ] } ? W ', 30));

        $this->post('/job-description/skills', ['jobDescription' => self::CALL_CENTRE_JOB, 'cv' => $pdf], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('score', 100);

        Http::assertSent(fn (Request $request) => data_get($request->data(), 'contents.0.parts.1.inline_data.mime_type') === 'application/pdf');
    }

    public function test_confirmed_requirements_reach_the_cv_and_pass_the_fact_guard(): void
    {
        $cvText = "Ayesha Khan\nayesha@example.com\nBackend developer. Worked with project managers and clients.\nEnglish (C1)\n"
            ."Senior Backend Developer, BrightCart (2023 - Present)\nMentored two junior developers.";
        $tailored = [
            'personal' => ['name' => 'Ayesha Khan', 'headline' => 'Client communication · Fluent English (C1) · Night shift availability'],
            'about' => 'Communicates clearly with clients and project managers.',
            'experience' => [['title' => 'Senior Backend Developer', 'company' => 'BrightCart', 'period' => '2023 - Present']],
            'skills' => [['label' => 'Key skills for this role', 'items' => ['Night shift availability', 'Full-time availability', 'Sales experience']]],
        ];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($this->answer(['cv' => $tailored, 'fit' => ['level' => 'weak', 'target_role' => 'Customer Sales Representative']]))
            // Coverage repair round: the summary and experience did not mention the confirmed requirements yet.
            ->push($this->answer([
                'headline' => 'Client communication · Fluent English (C1) · Night shift and full-time availability',
                'about' => 'Communicates clearly with clients and project managers. Available full-time, including night shifts.',
                'experience' => [['Answered customer and client questions with project managers.']],
            ]))
            ->whenEmpty(Http::response(['error' => ['message' => 'unexpected extra call']], 503))]);

        $response = $this->post('/cv/tailor', [
            'jobDescription' => self::CALL_CENTRE_JOB,
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', $cvText),
            'confirmed' => ['Night shift availability', 'Full-time availability', ' <b>Night shift availability</b> '],
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(fn (Request $request) => str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), "CANDIDATE CONFIRMED")
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), '- Full-time availability'));

        $record = app(TailoredCvRepository::class)->find($response->json('data.result'));
        $this->assertStringStartsWith("Confirmed by the candidate for this application:\n- Night shift availability\n- Full-time availability", $record['source']);
        // Confirmed items survive the fact guard; an unconfirmed claim the CV does not support does not.
        // Availability is not a skill: it gets its own short group.
        $this->assertSame('Availability', $record['cv']['skills'][0]['label']);
        $this->assertSame(['Night shift', 'Full-time'], $record['cv']['skills'][0]['items']);
        // The repair round made the summary and the experience reflect the requirements.
        $this->assertStringContainsString('night shifts', $record['cv']['about']);
        $this->assertSame(['Answered customer and client questions with project managers.'], $record['cv']['experience'][0]['highlights']);
        Http::assertSentCount(2);
    }

    public function test_without_the_ai_it_falls_back_to_keyword_matching(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        $this->post('/job-description/skills', [
            'jobDescription' => "Laravel developer\nRequirements:\n- PHP\n- Laravel\n- Docker",
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', str_repeat('PHP and Laravel developer. ', 5)),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('source', 'keywords')
            ->assertJsonPath('missing', ['Docker']);
    }

    private function answer(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }
}
