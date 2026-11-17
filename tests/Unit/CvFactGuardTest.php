<?php

namespace Tests\Unit;

use App\Services\CvFactGuard;
use PHPUnit\Framework\TestCase;

class CvFactGuardTest extends TestCase
{
    private const SOURCE = <<<'TEXT'
Sam Carter
Email: sam.carter@example.com | Phone: +00 111 2223333 | Lahore
Profile
Backend developer with 3 years of experience building web applications with PHP and Laravel.
Experience
Backend Developer, Example Soft (2022 - Present), Lahore
- Built REST APIs in Laravel used by mobile and web clients.
- Designed MySQL schemas and optimised slow queries, cutting page load time by 40%.
- Set up Docker-based local environments for the team.
Junior PHP Developer, Sample Labs (2021 - 2022)
- Maintained WordPress and custom PHP sites.
Education
BS Computer Science, Example University (2017 - 2021)
Skills
PHP, Laravel, MySQL, Docker, Git, REST APIs, JavaScript, HTML, CSS, C++, Node.js
Languages
English, Urdu
TEXT;

    private CvFactGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new CvFactGuard();
    }

    public function test_truthful_cv_passes_unchanged(): void
    {
        $cv = $this->cv();

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame($cv['experience'], $result['cv']['experience']);
        $this->assertSame($cv['skills'], $result['cv']['skills']);
        $this->assertSame([], $result['removed']);
    }

    public function test_guessed_sector_and_department_are_removed(): void
    {
        $cv = $this->cv();
        $cv['experience'][0]['business_sector'] = 'Software Development';
        $cv['experience'][0]['department'] = 'Engineering';

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame('', $result['cv']['experience'][0]['business_sector']);
        $this->assertSame('', $result['cv']['experience'][0]['department']);
        $this->assertCount(2, $result['removed']);
    }

    public function test_invented_employer_school_project_and_certificate_are_dropped(): void
    {
        $cv = $this->cv();
        $cv['experience'][] = ['title' => 'Tech Lead', 'company' => 'Google', 'period' => '2020 - 2021'];
        $cv['education'][] = ['degree' => 'MSc AI', 'institution' => 'Stanford University'];
        $cv['projects'] = [['title' => 'Kubernetes Operator', 'description' => 'Built a cluster autoscaler.']];
        $cv['certifications'] = [['title' => 'AWS Certified Solutions Architect']];

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame(['Example Soft', 'Sample Labs'], array_column($result['cv']['experience'], 'company'));
        $this->assertSame(['Example University'], array_column($result['cv']['education'], 'institution'));
        $this->assertSame([], $result['cv']['projects']);
        $this->assertSame([], $result['cv']['certifications']);
    }

    public function test_skills_must_be_in_the_cv_but_rewording_is_allowed(): void
    {
        $cv = $this->cv();
        $cv['skills'] = [['label' => 'Technical', 'items' => ['Laravel', 'REST API design', 'MySQL schemas', 'Vue.js', 'PostgreSQL', 'CI/CD', 'C++', 'Node.js']]];

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame(['Laravel', 'REST API design', 'MySQL schemas', 'C++', 'Node.js'], $result['cv']['skills'][0]['items']);
    }

    public function test_invented_metrics_and_dates_are_removed_but_real_ones_stay(): void
    {
        $cv = $this->cv();
        $cv['about'] = 'Backend developer with 3 years of experience. Served 2 million users across 12 countries.';
        $cv['experience'][0]['highlights'] = [
            'Cut page load time by 40% through query optimisation.',
            'Reduced server costs by 35%.',
        ];
        $cv['experience'][1]['period'] = '2019 - 2022';

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame('Backend developer with 3 years of experience.', $result['cv']['about']);
        $this->assertSame(['Cut page load time by 40% through query optimisation.'], $result['cv']['experience'][0]['highlights']);
        $this->assertSame('', $result['cv']['experience'][1]['period']);
    }

    public function test_a_digit_inside_the_phone_number_does_not_back_up_a_metric(): void
    {
        $cv = $this->cv();
        $cv['about'] = 'Improved conversion by 22%.';

        $this->assertSame('', $this->guard->enforce($cv, self::SOURCE)['cv']['about']);
    }

    public function test_numbers_inside_words_and_formatted_numbers_are_recognised(): void
    {
        $source = self::SOURCE."\nFixed N+1 queries in Laravel 10. English (C1). Served 1,000 customers. 3.4 CGPA.";
        $cv = $this->cv();
        $cv['experience'][0]['highlights'] = [
            'Fixed N+1 queries in Laravel 10, cutting load time by 40%.',
            'Served 1000 customers.',
            'Graduated with a 3.4 CGPA.',
            'Served 3,000 customers.',
        ];
        $cv['languages'] = ['mother' => '', 'other' => [['name' => 'English', 'listening' => 'C1']]];

        $result = $this->guard->enforce($cv, $source)['cv'];

        $this->assertSame(array_slice($cv['experience'][0]['highlights'], 0, 3), $result['experience'][0]['highlights']);
        $this->assertSame('C1', $result['languages']['other'][0]['listening']);
    }

    public function test_mother_tongue_is_not_guessed(): void
    {
        $cv = $this->cv();
        $cv['languages'] = ['mother' => 'Urdu', 'other' => [['name' => 'English', 'listening' => 'C2', 'reading' => 'C2']]];

        $languages = $this->guard->enforce($cv, self::SOURCE)['cv']['languages'];

        $this->assertSame('', $languages['mother']);
        $this->assertSame(['English', 'Urdu'], array_column($languages['other'], 'name'));
        $this->assertSame('', $languages['other'][0]['listening'], 'CEFR levels are not in the CV');
    }

    public function test_mother_tongue_is_kept_when_the_cv_states_it(): void
    {
        $cv = $this->cv();
        $cv['languages'] = ['mother' => 'Urdu', 'other' => [['name' => 'English']]];

        $languages = $this->guard->enforce($cv, self::SOURCE."\nMother tongue: Urdu")['cv']['languages'];

        $this->assertSame('Urdu', $languages['mother']);
    }

    public function test_unknown_contact_details_and_links_are_removed(): void
    {
        $cv = $this->cv();
        $cv['personal'] += ['nationality' => 'British', 'date_of_birth' => '1990-01-01'];
        $cv['experience'][0]['website'] = 'https://examplesoft.io';

        $result = $this->guard->enforce($cv, self::SOURCE);

        $this->assertSame('', $result['cv']['personal']['nationality']);
        $this->assertSame('', $result['cv']['personal']['date_of_birth']);
        $this->assertSame('sam.carter@example.com', $result['cv']['personal']['email']);
        $this->assertSame('+00 111 2223333', $result['cv']['personal']['phone']);
        $this->assertSame('', $result['cv']['experience'][0]['website']);
    }

    public function test_fact_checker_verdicts_are_applied_and_rechecked(): void
    {
        $cv = $this->cv();
        $cv['experience'][0]['summary'] = 'Led backend development initiatives for SaaS platforms.';
        $cv['experience'][0]['highlights'][] = 'Mentored a team of 5 engineers.';

        $claims = $this->guard->claims($cv);
        $this->assertArrayHasKey('experience.0.summary', $claims);
        $this->assertArrayHasKey('experience.0.highlights.2', $claims);

        $result = $this->guard->applyVerdicts($cv, [
            'experience.0.summary' => ['verdict' => 'fix', 'text' => 'Builds backend web applications with PHP and Laravel.'],
            'experience.0.highlights.2' => ['verdict' => 'remove'],
            // A "fix" that sneaks in a new number is still caught by the deterministic re-check.
            'about' => ['verdict' => 'fix', 'text' => 'Developer with 10 years of experience.'],
        ], self::SOURCE);

        $this->assertSame('Builds backend web applications with PHP and Laravel.', $result['cv']['experience'][0]['summary']);
        $this->assertCount(2, $result['cv']['experience'][0]['highlights']);
        $this->assertSame('', $result['cv']['about']);
    }

    private function cv(): array
    {
        return [
            'personal' => ['name' => 'Sam Carter', 'email' => 'sam.carter@example.com', 'phone' => '+00 111 2223333', 'address' => 'Lahore'],
            'about' => 'Backend developer with 3 years of experience building web applications with PHP and Laravel.',
            'education' => [['date' => '2017 - 2021', 'degree' => 'BS Computer Science', 'institution' => 'Example University', 'location' => '', 'details' => '']],
            'experience' => [
                [
                    'title' => 'Backend Developer', 'company' => 'Example Soft', 'period' => '2022 - Present', 'location' => 'Lahore',
                    'website' => '', 'business_sector' => '', 'department' => '', 'summary' => 'Builds Laravel REST APIs for mobile and web clients.',
                    'highlights' => ['Built REST APIs in Laravel used by mobile and web clients.', 'Designed MySQL schemas, cutting page load time by 40%.'],
                ],
                [
                    'title' => 'Junior PHP Developer', 'company' => 'Sample Labs', 'period' => '2021 - 2022', 'location' => '',
                    'website' => '', 'business_sector' => '', 'department' => '', 'summary' => 'Maintained WordPress and custom PHP sites.',
                    'highlights' => [],
                ],
            ],
            'projects' => [],
            'skills' => [['label' => 'Technical', 'items' => ['PHP', 'Laravel', 'MySQL', 'Docker', 'REST APIs']]],
            'languages' => ['mother' => '', 'other' => [['name' => 'English'], ['name' => 'Urdu']]],
            'certifications' => [],
            'interests' => [],
            'driving' => '',
            'hobbies' => '',
        ];
    }
}
