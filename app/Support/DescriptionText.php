<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Comic descriptions: plain text that may carry links — the
 * `[label](https://…)` the E-Hentai importer writes, plus any bare URL
 * someone pasted. Escape-first, then linkify (same idea as PostText),
 * but the output stays inline so it drops straight into the `<p>` tags
 * the card, the list row and the reader already have.
 */
class DescriptionText
{
    /** `[label](url)` first, bare URL second. */
    protected const PATTERN = '#\[([^\]\n]{1,120})\]\((https?://[^\s\)]+)\)|(https?://[^\s<]+)#i';

    /**
     * Safe HTML: everything is escaped; links become markup when $linkify is true.
     * Ready to echo unescaped in Blade: {!! DescriptionText::render($x) !!}
     */
    public static function render(?string $text, bool $linkify = true): string
    {
        $text = (string) $text;

        if (trim($text) === '') {
            return '';
        }

        if (! $linkify) {
            return nl2br(e(self::plain($text)));
        }

        if (preg_match_all(self::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return nl2br(e($text));
        }

        $html = '';
        $cursor = 0;

        foreach ($matches[0] as $i => [$token, $offset]) {
            $html .= nl2br(e(substr($text, $cursor, $offset - $cursor)));

            // `[label](url)` fills groups 1+2; a bare URL fills group 3
            // and is the whole token.
            if (($matches[2][$i][0] ?? '') !== '') {
                $html .= self::anchor($matches[2][$i][0], $matches[1][$i][0]);
            } else {
                // Trailing sentence punctuation stays outside the link.
                $url = rtrim($token, '.,;:!?)\]}');
                $html .= self::anchor($url, self::shortUrl($url)).e(substr($token, strlen($url)));
            }

            $cursor = $offset + strlen($token);
        }

        return $html.nl2br(e(substr($text, $cursor)));
    }

    /**
     * The same text without link markup, for excerpts (Str::limit…).
     */
    public static function plain(?string $text): string
    {
        return trim((string) preg_replace(
            '#\[([^\]\n]{1,120})\]\((?:https?://[^\s\)]+)\)#i',
            '$1',
            (string) $text
        ));
    }

    protected static function anchor(string $url, string $label): string
    {
        return '<a class="desc-link" href="'.e($url).'" target="_blank" rel="noopener nofollow">'.e($label).'</a>';
    }

    /** Link text for a bare URL: no scheme, not too long. */
    protected static function shortUrl(string $url): string
    {
        return (string) Str::of((string) preg_replace('#^https?://#i', '', $url))->limit(42);
    }
}
