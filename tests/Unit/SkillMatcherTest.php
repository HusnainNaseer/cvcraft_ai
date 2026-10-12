<?php

namespace Tests\Unit;

use App\Services\SkillMatcher;
use PHPUnit\Framework\TestCase;

class SkillMatcherTest extends TestCase
{
    public function test_score_reflects_the_real_overlap(): void
    {
        $job = "Requirements:\n- PHP\n- Laravel\n- Docker\n- AWS";
        $cv = 'Backend developer with 3 years of PHP and Laravel.';

        $match = (new SkillMatcher())->match($job, $cv);

        $this->assertSame(['PHP', 'Laravel'], $match['matched']);
        $this->assertContains('Docker', $match['missing']);
        $this->assertContains('AWS', $match['missing']);
        $this->assertSame(50, $match['score']);
    }
}
