<?php

namespace App\Support;

use App\Models\Comic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Twitter-style link cards for post URLs. Only the first URL previews:
 * YouTube (oEmbed title + thumbnail), internal comic links (cover card),
 * anything else (compact host card). oEmbed responses cache for 12h.
 *
 * @return array{type: string, url: string}|array{type: 'youtube', url: string, video_id: string, title: string, thumbnail: string}|array{type: 'comic', url: string, title: string, author: string|null, cover: string|null, link: string}|array{type: 'link', url: string, host: string}|null
 */
class LinkPreview
{
    public static function forText(?string $text): ?array
    {
        $urls = PostText::extractUrls($text);
        if ($urls === []) {
            return null;
        }

        return self::forUrl($urls[0]);
    }

    public static function forUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if (! isset($parts['host'], $parts['path'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'];

        // Internal comic links: /comics/{slug} or /comics/id/{id}.
        if (preg_match('#^/comics/id/(\d+)$#', $path, $m)) {
            $comic = Comic::find($m[1]);
            if ($comic !== null) {
                return self::comicCard($url, $comic);
            }

            return null;
        }
        if (preg_match('#^/comics/([^/]+)$#', $path, $m) && ! in_array($m[1], ['create', 'search'], true)) {
            $comic = Comic::where('slug', $m[1])->first();
            if ($comic !== null) {
                return self::comicCard($url, $comic);
            }

            return null;
        }

        // YouTube: title via oEmbed, thumbnail is deterministic.
        $videoId = self::youtubeId($host, $path, $parts['query'] ?? '');
        if ($videoId !== null) {
            return Cache::remember('linkpreview:yt:'.$videoId, now()->addHours(12), function () use ($url, $videoId) {
                $title = 'YouTube video';
                try {
                    $res = Http::timeout(3)->acceptJson()->get('https://www.youtube.com/oembed', [
                        'url' => $url,
                        'format' => 'json',
                    ]);
                    if ($res->ok() && is_string($res->json('title')) && $res->json('title') !== '') {
                        $title = $res->json('title');
                    }
                } catch (\Throwable) {
                    // Offline host or blocked egress: thumbnail still renders.
                }

                return [
                    'type' => 'youtube',
                    'url' => $url,
                    'video_id' => $videoId,
                    'title' => $title,
                    'thumbnail' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
                ];
            });
        }

        // Generic external link: compact host card (no fetching).
        return [
            'type' => 'link',
            'url' => $url,
            'host' => $parts['host'],
        ];
    }

    protected static function youtubeId(string $host, string $path, string $query): ?string
    {
        if (in_array($host, ['youtu.be'], true)) {
            $id = trim($path, '/');

            return preg_match('#^[A-Za-z0-9_-]{6,}$#', $id) ? $id : null;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($query, $params);

                return isset($params['v']) && preg_match('#^[A-Za-z0-9_-]{6,}$#', $params['v']) ? $params['v'] : null;
            }
            if (preg_match('#^/(embed|shorts|live)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
                return $m[2];
            }
        }

        return null;
    }

    protected static function comicCard(string $url, Comic $comic): array
    {
        return [
            'type' => 'comic',
            'url' => $url,
            'title' => $comic->title,
            'author' => $comic->author,
            'cover' => $comic->image_path ? asset('storage/'.$comic->image_path) : null,
            'link' => route('comics.showBySlug', ['slug' => $comic->slug]),
        ];
    }
}
