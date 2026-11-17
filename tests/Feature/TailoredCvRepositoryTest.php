<?php

namespace Tests\Feature;

use App\Services\TailoredCvRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TailoredCvRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TailoredCvRepository $results;

    protected function setUp(): void
    {
        parent::setUp();

        $this->results = app(TailoredCvRepository::class);
    }

    public function test_it_stores_and_finds_a_tailored_cv(): void
    {
        $cv = ['personal' => ['name' => 'Zoë Müller', 'photo' => 'data:image/png;base64,AAAA'], 'about' => 'Builds APIs/services.'];
        $job = ['title' => 'Laravel Developer', 'emails' => ['careers@acme.io']];

        $token = $this->results->create($cv, $job, 'Zoë Müller, API developer');

        $this->assertMatchesRegularExpression(TailoredCvRepository::TOKEN_PATTERN, $token);
        $this->assertSame(['cv' => $cv, 'job' => $job, 'source' => 'Zoë Müller, API developer'], $this->results->find($token));
    }

    public function test_unknown_or_malformed_tokens_are_not_found(): void
    {
        $this->assertNull($this->results->find(str_repeat('a', 40)));
        $this->assertNull($this->results->find("x' OR '1'='1"));
    }

    public function test_update_replaces_the_cv_and_keeps_the_job(): void
    {
        $token = $this->results->create(['about' => 'old'], ['title' => 'Dev']);

        $this->results->updateCv($token, ['about' => 'new']);

        $this->assertSame(['cv' => ['about' => 'new'], 'job' => ['title' => 'Dev'], 'source' => ''], $this->results->find($token));
    }

    public function test_updating_a_missing_cv_fails(): void
    {
        $this->expectException(RuntimeException::class);

        $this->results->updateCv(str_repeat('b', 40), ['about' => 'new']);
    }

    public function test_prune_deletes_only_old_results(): void
    {
        $old = $this->results->create(['about' => 'old'], []);
        $recent = $this->results->create(['about' => 'recent'], []);
        DB::table('tailored_cvs')->where('token', $old)->update(['updated_at' => now()->subDays(31)]);

        $this->assertSame(1, $this->results->prune(30));
        $this->assertNull($this->results->find($old));
        $this->assertNotNull($this->results->find($recent));
    }
}
