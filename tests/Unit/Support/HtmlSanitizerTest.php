<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/** Cleaning doc HTML and turning it into plain text. */
class HtmlSanitizerTest extends TestCase
{
    public function test_scripts_event_handlers_and_javascript_links_are_removed(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="steal()">Hi <a href="javascript:alert(1)">there</a></p><script>alert(1)</script>');

        $this->assertStringContainsString('Hi', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_safe_formatting_and_links_are_kept(): void
    {
        $clean = HtmlSanitizer::clean('<p><strong>Bold</strong> <a href="https://example.com">link</a></p>');

        $this->assertStringContainsString('<strong>Bold</strong>', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
    }

    public function test_plain_text_puts_spaces_between_blocks_and_decodes_entities(): void
    {
        $this->assertSame('One Two & three', HtmlSanitizer::text('<p>One</p><p>Two &amp; three</p>'));
        $this->assertSame('', HtmlSanitizer::text(null));
    }
}
