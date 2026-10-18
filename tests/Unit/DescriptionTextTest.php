<?php

namespace Tests\Unit;

use App\Support\DescriptionText;
use Tests\TestCase;

class DescriptionTextTest extends TestCase
{
    public function test_renders_a_link_with_the_uploader_as_its_text(): void
    {
        $html = DescriptionText::render('Importado do E-Hentai (Doujinshi) — [someone](https://e-hentai.org/g/123/abcdef1234/)');

        $this->assertStringContainsString(
            '<a class="desc-link" href="https://e-hentai.org/g/123/abcdef1234/" target="_blank" rel="noopener nofollow">someone</a>',
            $html
        );
        $this->assertStringContainsString('Importado do E-Hentai (Doujinshi) —', $html);
    }

    public function test_linkifies_bare_urls_leaving_trailing_punctuation_out(): void
    {
        $html = DescriptionText::render('fonte (https://e-hentai.org/g/1/aa/).');

        $this->assertStringContainsString('href="https://e-hentai.org/g/1/aa/"', $html);
        $this->assertStringContainsString('>e-hentai.org/g/1/aa/</a>).', $html);
    }

    public function test_escapes_html_and_ignores_unsafe_schemes(): void
    {
        $html = DescriptionText::render('<script>alert(1)</script> [x](javascript:alert(1))');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    public function test_escapes_a_quoted_breakout_attempt_inside_the_label(): void
    {
        $html = DescriptionText::render('[a" onmouseover="alert(1)](https://e-hentai.org/g/1/aa/)');

        $this->assertStringNotContainsString('onmouseover="alert(1)"', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_keeps_line_breaks_and_handles_empty_input(): void
    {
        $this->assertStringContainsString('<br />', DescriptionText::render("linha 1\nlinha 2"));
        $this->assertSame('', DescriptionText::render(null));
        $this->assertSame('', DescriptionText::render('   '));
    }

    public function test_plain_strips_link_markup_for_excerpts(): void
    {
        $this->assertSame(
            'Importado do E-Hentai — someone',
            DescriptionText::plain('Importado do E-Hentai — [someone](https://e-hentai.org/g/1/aa/)')
        );
        $this->assertSame('', DescriptionText::plain(null));
    }
}
