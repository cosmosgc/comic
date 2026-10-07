<?php

namespace Tests\Unit;

use App\Support\Markdown;
use Tests\TestCase;

class MarkdownTest extends TestCase
{
    public function test_renders_core_elements(): void
    {
        $html = Markdown::toHtml("# Title\n\nSome **bold** and *italic* text.\n\n- one\n- two\n");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<ul>', $html);
    }

    public function test_renders_gfm_tables_strikethrough_and_autolinks(): void
    {
        $html = Markdown::toHtml("| A | B |\n|---|---|\n| 1 | 2 |\n\n~~gone~~\n\nVisit https://example.com\n");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<del>gone</del>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_renders_fenced_code_blocks(): void
    {
        $html = Markdown::toHtml("```php\necho 'hi';\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
        $this->assertStringContainsString("echo 'hi';", $html);
    }

    public function test_escapes_raw_html(): void
    {
        $html = Markdown::toHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_neutralizes_unsafe_links(): void
    {
        $html = Markdown::toHtml('[x](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_empty_input_renders_empty(): void
    {
        $this->assertSame('', Markdown::toHtml(''));
        $this->assertSame('', Markdown::toHtml(null));
        $this->assertSame('', Markdown::toHtml('   '));
    }
}
