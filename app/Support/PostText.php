<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Rich post text: raw user input becomes safe HTML with clickable
 * URLs and @mentions. Escape-first, then linkify on segments.
 */
class PostText
{
    /** @var array<string, array<string, string>> Per-request mention cache. */
    protected static array $mentionCache = [];

    public static function render(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $parts = preg_split('#(https?://[^\s<]+)#i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $mentions = self::resolveMentions($text);

        $out = '';
        foreach ($parts as $i => $chunk) {
            if ($i % 2 === 1) {
                $out .= self::linkTag($chunk);
            } else {
                $out .= self::renderMentions($chunk, $mentions);
            }
        }

        return $out;
    }

    /**
     * @return string[] Raw URLs in order of appearance.
     */
    public static function extractUrls(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        preg_match_all('#https?://[^\s<]+#i', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $url) => rtrim($url, '.,;:!?)\]}'),
            $matches[0]
        )));
    }

    /**
     * @return string[] Mention tokens without the @.
     */
    public static function extractMentions(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        preg_match_all('#@([A-Za-z0-9_]+)#', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Map lowercase token => display name for users that exist.
     *
     * @return array<string, string>
     */
    protected static function resolveMentions(string $text): array
    {
        $tokens = self::extractMentions($text);
        if ($tokens === []) {
            return [];
        }

        $key = implode("\0", $tokens);
        if (! array_key_exists($key, self::$mentionCache)) {
            $lower = array_map('strtolower', $tokens);
            $names = User::whereIn('name', $tokens)->pluck('name')->all();
            $resolved = [];
            foreach ($names as $name) {
                $resolved[strtolower($name)] = $name;
            }
            // Handle-style tokens (@johndoe for "John Doe").
            $missing = array_values(array_diff($lower, array_keys($resolved)));
            if ($missing !== []) {
                $placeholders = implode(',', array_fill(0, count($missing), '?'));
                $slugs = User::whereRaw("REPLACE(LOWER(name), ' ', '') IN (".$placeholders.')', $missing)
                    ->pluck('name')
                    ->all();
                foreach ($slugs as $name) {
                    $resolved[(string) Str::of($name)->lower()->replace(' ', '')] = $name;
                }
            }
            self::$mentionCache[$key] = $resolved;
        }

        return self::$mentionCache[$key];
    }

    protected static function renderMentions(string $chunk, array $mentions): string
    {
        $parts = preg_split('#@([A-Za-z0-9_]+)#', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE);

        $out = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $name = $mentions[strtolower($part)] ?? null;
                $out .= $name !== null
                    ? '<a href="'.e(route('profile.public.show.username', ['username' => $name])).'" data-hovercard="'.e($name).'" class="text-sky-500 hover:underline">@'.e($name).'</a>'
                    : '@'.e($part);
            } else {
                $out .= e($part);
            }
        }

        return $out;
    }

    protected static function linkTag(string $url): string
    {
        $trailing = '';
        $clean = rtrim($url, '.,;:!?)\]}');
        if (strlen($clean) !== strlen($url)) {
            $trailing = e(substr($url, strlen($clean)));
        }

        $display = (string) Str::of(preg_replace('#^https?://#i', '', $clean))->limit(42);

        return '<a href="'.e($clean).'" target="_blank" rel="noopener nofollow" class="text-sky-500 hover:underline">'.e($display).'</a>'.$trailing;
    }
}
