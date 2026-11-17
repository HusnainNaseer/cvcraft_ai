<?php

namespace Tests\Feature;

use App\Exceptions\GeminiException;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private const CV = "Jane Doe\njane@example.net\nPHP and Laravel developer.\nExperience\nDeveloper, Widgets Ltd (2022 - 2025)\n"
        ."Built Laravel APIs in PHP for the sales team.\nSkills: PHP, Laravel";

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.gemini.api_key' => 'key-one',
            'services.gemini.fallback_api_key' => null,
            'services.gemini.api_keys' => 'key-two',
            'services.gemini.models' => 'model-a,model-b',
        ]);
    }

    public function test_an_unreadable_file_is_retried_once_from_the_extracted_text(): void
    {
        $cv = ['personal' => ['name' => 'Jane Doe'], 'about' => 'PHP and Laravel developer.',
            'experience' => [['title' => 'Developer', 'company' => 'Widgets Ltd', 'period' => '2022 - 2025']]];

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['code' => 400, 'message' => 'The document has no pages.', 'status' => 'INVALID_ARGUMENT']], 400)
            ->push($this->answer(['cv' => $cv, 'reasoning' => 'ok']))
            ->push($this->answer(['results' => [['id' => 'c1', 'verdict' => 'keep'], ['id' => 'c2', 'verdict' => 'keep']]]))]);

        $this->post('/cv/tailor', [
            'jobDescription' => str_repeat('Laravel developer with PHP. ', 4),
            'cv' => UploadedFile::fake()->createWithContent('cv.txt', self::CV),
        ], ['Accept' => 'application/json'])->assertOk();

        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(2, $requests, 'one rejected file request, then one text-only retry');
        $this->assertNotNull(data_get($requests[0]->data(), 'contents.0.parts.1.inline_data'));
        $this->assertNull(data_get($requests[1]->data(), 'contents.0.parts.1'), 'the retry sends text only');
    }

    public function test_a_bad_request_is_not_repeated_on_every_key_and_model(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'Invalid JSON payload.']], 400)]);

        try {
            app(GeminiService::class)->verifyClaims(['about' => 'Developer.'], self::CV);
            $this->fail('expected a GeminiException');
        } catch (GeminiException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringContainsString('could not read this CV file', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_an_invalid_api_key_moves_on_to_the_next_key(): void
    {
        Http::fake(function (Request $request) {
            return $request->header('X-goog-api-key')[0] === 'key-one'
                ? Http::response(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.',
                    'details' => [['reason' => 'API_KEY_INVALID']]]], 400)
                : Http::response($this->answer(['results' => [['id' => 'c1', 'verdict' => 'keep']]]));
        });

        $review = app(GeminiService::class)->verifyClaims(['about' => 'Developer.'], self::CV);

        $this->assertTrue($review['complete']);
        Http::assertSentCount(2);
    }

    private function answer(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }
}
