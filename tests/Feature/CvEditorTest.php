<?php

namespace Tests\Feature;

use App\Services\TailoredCvRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvEditorTest extends TestCase
{
    use RefreshDatabase;

    private TailoredCvRepository $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->results = app(TailoredCvRepository::class);
    }

    public function test_privacy_page_and_editor_markup_load(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy policy')->assertSee('gmail.send');
        $this->get('/')->assertOk()->assertSee('Edit your CV')->assertSee('How CVCraft works')->assertDontSee('fa-file-user');
    }

    public function test_editor_loads_the_cv_without_photo_theme_or_meta(): void
    {
        $token = $this->results->create($this->cv(), ['description' => 'Laravel developer with PHP and Docker']);

        $cv = $this->getJson('/cv/content?result='.$token)->assertOk()->json('cv');

        $this->assertSame('Jane Doe', $cv['personal']['name']);
        $this->assertArrayNotHasKey('photo', $cv['personal']);
        $this->assertArrayNotHasKey('theme', $cv);
        $this->assertArrayNotHasKey('meta', $cv);
        $this->assertSame(['Built Laravel APIs.'], $cv['experience'][0]['highlights']);
    }

    public function test_saving_edits_updates_the_cv_and_keeps_photo_and_theme(): void
    {
        $token = $this->results->create($this->cv(), ['description' => 'Laravel developer with PHP and Docker']);

        $response = $this->postJson('/cv/content', [
            'result' => $token,
            'cv' => [
                'personal' => ['name' => '  Jane   Doe ', 'email' => 'jane@example.net', 'photo' => 'data:image/png;base64,EVIL'],
                'about' => 'Laravel developer. <script>alert(1)</script>',
                'experience' => [
                    ['title' => 'Developer', 'company' => 'Widgets Ltd', 'highlights' => ['Built Laravel APIs.', '', 'Set up Docker for the team.']],
                    ['title' => '', 'company' => ''],
                ],
                'skills' => [['label' => 'Technical', 'items' => ['PHP', 'Laravel', 'Docker', 'PHP']]],
                'languages' => ['mother' => '', 'other' => [['name' => 'English'], ['name' => '']]],
                'theme' => ['name' => 'hacked'],
                'unknown' => 'dropped',
            ],
        ])->assertOk();

        $this->assertSame([], $response->json('match.missing'), 'Docker now appears in the CV');

        $cv = $this->results->find($token)['cv'];
        $this->assertSame('Jane Doe', $cv['personal']['name']);
        $this->assertSame('data:image/png;base64,REAL', $cv['personal']['photo']);
        $this->assertSame('blue', $cv['theme']['name']);
        $this->assertSame('Laravel developer. alert(1)', $cv['about']);
        $this->assertCount(1, $cv['experience'], 'empty entries are dropped');
        $this->assertSame(['Built Laravel APIs.', 'Set up Docker for the team.'], $cv['experience'][0]['highlights']);
        $this->assertSame(['PHP', 'Laravel', 'Docker'], $cv['skills'][0]['items']);
        $this->assertSame([['name' => 'English', 'listening' => '', 'reading' => '', 'speaking_production' => '', 'speaking_interaction' => '', 'writing' => '']], $cv['languages']['other']);
        $this->assertArrayNotHasKey('unknown', $cv);
    }

    public function test_name_is_required_and_unknown_results_are_rejected(): void
    {
        $token = $this->results->create($this->cv(), []);

        $this->postJson('/cv/content', ['result' => $token, 'cv' => ['personal' => ['name' => ' ']]])->assertStatus(422);
        $this->postJson('/cv/content', ['result' => str_repeat('a', 40), 'cv' => ['personal' => ['name' => 'X']]])->assertNotFound();
        $this->getJson('/cv/content?result=bad')->assertStatus(422);
    }

    private function cv(): array
    {
        return [
            'meta' => ['template_name' => 'Europass Classic'],
            'theme' => ['name' => 'blue', 'primary' => '#1D4ED8', 'primary_light' => '#60A5FA'],
            'personal' => ['name' => 'Jane Doe', 'email' => 'jane@example.net', 'photo' => 'data:image/png;base64,REAL'],
            'about' => 'Laravel developer.',
            'experience' => [['title' => 'Developer', 'company' => 'Widgets Ltd', 'highlights' => ['Built Laravel APIs.']]],
            'skills' => [['label' => 'Technical', 'items' => ['PHP', 'Laravel']]],
            'languages' => ['mother' => '', 'other' => [['name' => 'English']]],
        ];
    }
}
