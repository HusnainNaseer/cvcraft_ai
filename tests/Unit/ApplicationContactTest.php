<?php

namespace Tests\Unit;

use App\Services\ApplicationContact;
use PHPUnit\Framework\TestCase;

class ApplicationContactTest extends TestCase
{
    public function test_it_finds_application_emails_and_ranks_hiring_addresses_first(): void
    {
        $text = 'Questions: info@acme.io. Send your CV to Careers@Acme.io. '
            .'Do not reply to noreply@acme.io. Logo: banner@2x.png';

        $this->assertSame(['careers@acme.io', 'info@acme.io'], ApplicationContact::emails($text));
    }

    public function test_email_wins_over_links_when_resolving_the_method(): void
    {
        $resolved = ApplicationContact::resolve([
            'url' => 'https://www.linkedin.com/jobs/view/123456789',
            'emails' => ['hr@acme.io'],
        ]);

        $this->assertSame('email', $resolved['method']);
        $this->assertSame('hr@acme.io', $resolved['email']);
    }

    public function test_linkedin_and_website_methods(): void
    {
        $this->assertSame('linkedin', ApplicationContact::resolve(['url' => 'https://www.linkedin.com/jobs/view/1'])['method']);

        $website = ApplicationContact::resolve(['url' => 'https://acme.io/jobs/1', 'applyUrl' => 'https://acme.io/apply/1']);
        $this->assertSame('website', $website['method']);
        $this->assertSame('https://acme.io/apply/1', $website['applyUrl']);

        $this->assertSame('manual', ApplicationContact::resolve([])['method']);
    }
}
