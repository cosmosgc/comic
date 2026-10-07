<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * GitHub-flavored Markdown → HTML for content pages (changelog entries).
 *
 * Hardened for stored content: raw HTML is escaped (not passed through)
 * and unsafe link schemes (javascript:, data:, …) are neutralized.
 * Output is safe to echo unescaped in Blade: {!! Markdown::toHtml($body) !!}
 */
class Markdown
{
    protected static ?MarkdownConverter $converter = null;

    public static function toHtml(?string $markdown): string
    {
        if (trim((string) $markdown) === '') {
            return '';
        }

        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);

            self::$converter = new MarkdownConverter($environment);
        }

        return (string) self::$converter->convert($markdown);
    }
}
