<?php

namespace Tests\Unit;

use App\Services\CvUploadParser;
use PHPUnit\Framework\TestCase;

class CvUploadParserTest extends TestCase
{
    public function test_real_text_in_any_script_is_recognised(): void
    {
        $parser = new CvUploadParser();

        $this->assertTrue($parser->looksLikeText(str_repeat('Backend developer with five years of experience building Laravel APIs. ', 3)));
        $this->assertTrue($parser->looksLikeText(str_repeat('میں ایک سافٹ ویئر ڈویلپر ہوں اور لاراویل میں کام کرتا ہوں ', 3)));
    }

    public function test_pdf_glyph_codes_are_not_mistaken_for_text(): void
    {
        $garbage = 'fi fi ? ? Z < Z v ^ v ] } ? W , W l > ? ? o ? o } ? ? ? ? Z X l Z v ? ? u ? o X } u n = ? ? ? ? ? ? n > Z } ? U W l ] ? ? v o ] v';

        $this->assertFalse((new CvUploadParser())->looksLikeText($garbage));
    }
}
