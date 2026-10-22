<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EhTagLookupService
{
    /**
     * Parse an E-Hentai / ExHentai gallery URL.
     *
     * @return array{gid: int, token: string}|null
     */
    public function parseGalleryUrl(string $input): ?array
    {
        if (preg_match('#https?://(?:e-hentai|exhentai)\.org/g/(\d+)/([a-f0-9]{10})#i', trim($input), $m) !== 1) {
            return null;
        }

        return ['gid' => (int) $m[1], 'token' => strtolower($m[2])];
    }

    /**
     * Parse an image-page URL (/s/<page_token>/<gid>-<page>).
     *
     * @return array{gid: int, page_token: string, page: int}|null
     */
    public function parsePageUrl(string $input): ?array
    {
        if (preg_match('#https?://(?:e-hentai|exhentai)\.org/s/([a-f0-9]{10})/(\d+)-(\d+)#i', trim($input), $m) !== 1) {
            return null;
        }

        return ['page_token' => strtolower($m[1]), 'gid' => (int) $m[2], 'page' => (int) $m[3]];
    }

    /**
     * Resolve gallery metadata via the `gdata` API method.
     *
     * @param  array<int, array{gid: int, token: string}>  $pairs
     * @return array<int, array<string, mixed>>
     */
    public function fetchMetadata(array $pairs): array
    {
        $pairs = array_values(array_slice($pairs, 0, 25));
        if ($pairs === []) {
            return [];
        }

        $key = 'eh:gdata:'.md5(json_encode($pairs));
        $ttl = (int) config('taglookup.cache_ttl', 3600);

        return Cache::remember($key, $ttl, function () use ($pairs) {
            $response = Http::timeout((int) config('taglookup.timeout', 10))
                ->acceptJson()
                ->post((string) config('taglookup.api_url'), [
                    'method' => 'gdata',
                    'gidlist' => array_map(fn ($p) => [$p['gid'], $p['token']], $pairs),
                    'namespace' => 1,
                ]);

            if (! $response->ok()) {
                return [['error' => 'E-Hentai API returned HTTP '.$response->status()]];
            }

            return $response->json('gmetadata', []);
        });
    }

    /**
     * Resolve a gallery token from an image-page URL via `gtoken`.
     */
    public function resolvePageToken(string $pageToken, int $gid, int $page): ?string
    {
        $key = "eh:gtoken:{$gid}:{$pageToken}:{$page}";

        return Cache::remember($key, (int) config('taglookup.cache_ttl', 3600), function () use ($pageToken, $gid, $page) {
            $response = Http::timeout((int) config('taglookup.timeout', 10))
                ->acceptJson()
                ->post((string) config('taglookup.api_url'), [
                    'method' => 'gtoken',
                    'pagelist' => [[$gid, $pageToken, $page]],
                ]);

            if (! $response->ok()) {
                return null;
            }

            $token = $response->json('tokenlist.0.token');

            return is_string($token) ? $token : null;
        });
    }

    /**
     * Search e-hentai.org HTML for a title and return gid/token pairs.
     *
     * @return array<int, array{gid: int, token: string}>
     */
    public function searchGalleries(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $base = rtrim((string) config('taglookup.search_url', 'https://e-hentai.org/'), '/').'/';
        $key = 'eh:search:'.md5($base.$query);

        return Cache::remember($key, (int) config('taglookup.cache_ttl', 3600), function () use ($base, $query) {
            $response = Http::timeout((int) config('taglookup.timeout', 10))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Laravel comic tag lookup)'])
                ->withCookies($this->authCookies(), '.e-hentai.org')
                ->get($base, ['f_search' => $query]);

            if (! $response->ok()) {
                return [];
            }

            preg_match_all('#/(?:e-hentai|exhentai)\.org/g/(\d+)/([a-f0-9]{10})#i', $response->body(), $m, PREG_SET_ORDER);

            $pairs = [];
            $seen = [];
            foreach ($m as $match) {
                $gid = (int) $match[1];
                $token = strtolower($match[2]);
                if (isset($seen[$gid])) {
                    continue;
                }
                $seen[$gid] = true;
                $pairs[] = ['gid' => $gid, 'token' => $token];
                if (count($pairs) >= max(1, (int) config('taglookup.lookup_limit', 3))) {
                    break;
                }
            }

            return $pairs;
        });
    }

    /**
     * Full lookup: gallery URL (or image-page URL) or free-text title.
     *
     * @return array{source: string, pairs: array, galleries: array}
     */
    public function lookup(string $input): array
    {
        $gallery = $this->parseGalleryUrl($input);
        if ($gallery !== null) {
            return [
                'source' => 'url',
                'pairs' => [$gallery],
                'galleries' => $this->fetchMetadata([$gallery]),
            ];
        }

        $page = $this->parsePageUrl($input);
        if ($page !== null) {
            $token = $this->resolvePageToken($page['page_token'], $page['gid'], $page['page']);
            if ($token === null) {
                return ['source' => 'url', 'pairs' => [], 'galleries' => [['gid' => $page['gid'], 'error' => 'Could not resolve gallery token for that page URL.']]];
            }
            $pair = ['gid' => $page['gid'], 'token' => $token];

            return ['source' => 'url', 'pairs' => [$pair], 'galleries' => $this->fetchMetadata([$pair])];
        }

        $pairs = $this->searchGalleries($input);

        return ['source' => 'search', 'pairs' => $pairs, 'galleries' => $this->fetchMetadata($pairs)];
    }

    /**
     * Split "namespace:name" tags into parts for matching/display.
     *
     * @param  array<int, string>  $tags
     * @return array<int, array{full: string, namespace: string|null, name: string}>
     */
    public static function normalizeTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            if (str_contains($raw, ':')) {
                [$ns, $name] = explode(':', $raw, 2);
                $out[] = ['full' => $raw, 'namespace' => trim($ns) !== '' ? trim($ns) : null, 'name' => trim($name) !== '' ? trim($name) : $raw];
            } else {
                $out[] = ['full' => $raw, 'namespace' => null, 'name' => $raw];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function authCookies(): array
    {
        $cookies = [];
        if (config('taglookup.member_id') && config('taglookup.pass_hash')) {
            $cookies['ipb_member_id'] = (string) config('taglookup.member_id');
            $cookies['ipb_pass_hash'] = (string) config('taglookup.pass_hash');
        }
        if (config('taglookup.igneous')) {
            $cookies['igneous'] = (string) config('taglookup.igneous');
        }

        return $cookies;
    }
}
