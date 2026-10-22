<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Scrapes E-Hentai search result pages for the admin bulk importer.
 *
 * E-Hentai paginates searches with cursors (`?next=<gid>` / `?prev=<gid>`)
 * instead of page numbers, so the result page carries the cursors for the
 * neighbour pages and we pass them back on the next request. A pasted
 * search URL (e.g. `?f_search=language:portuguese$ furry&next=3657960`)
 * is parsed down to the same query + cursor pair.
 */
class EhGallerySearch
{
    public function __construct(protected EhTagLookupService $lookup) {}

    /**
     * Accepts either a free-text query or a full E-Hentai search URL.
     *
     * @return array{query: string, next: int|null, prev: int|null}
     */
    public function parseSearchInput(string $input): array
    {
        $input = trim($input);

        // Root search URL (optionally with query string). Gallery/page
        // URLs have a path and fall through as plain text; the controller
        // redirects those to the single-gallery import form.
        if (preg_match('#^https?://(?:e-hentai|exhentai)\.org/(?:\?(.*))?$#i', $input, $m) !== 1) {
            return ['query' => $input, 'next' => null, 'prev' => null];
        }

        $queryString = $m[1] ?? '';
        parse_str(html_entity_decode($queryString, ENT_QUOTES | ENT_HTML5), $params);

        return [
            'query' => trim((string) ($params['f_search'] ?? '')),
            'next' => $this->positiveInt($params['next'] ?? null),
            'prev' => $this->positiveInt($params['prev'] ?? null),
        ];
    }

    /**
     * Fetch one page of results.
     *
     * @return array{items: array<int, array{gid: int, token: string, title: string, thumb: string|null, category: string|null, pages: int|null, posted: string|null, uploader: string|null, source_url: string}>, next: int|null, prev: int|null, found: int|null, url: string}
     *
     * @throws EhImportException
     */
    public function search(string $query, ?int $next = null, ?int $prev = null): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new EhImportException('Digite um termo de busca ou cole a URL de uma busca do E-Hentai.');
        }

        $base = rtrim((string) config('taglookup.search_url', 'https://e-hentai.org/'), '/');
        $params = ['f_search' => $query];
        if ($next !== null && $next > 0) {
            $params['next'] = $next;
        } elseif ($prev !== null && $prev > 0) {
            $params['prev'] = $prev;
        }
        $url = $base.'/?'.http_build_query($params);

        $cacheKey = 'eh:search:page:'.md5($url);
        $html = Cache::remember($cacheKey, 300, function () use ($url): string {
            try {
                $response = Http::timeout((int) config('taglookup.timeout', 10))
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Laravel comic importer)'])
                    ->withCookies($this->lookup->authCookies(), '.e-hentai.org')
                    ->get($url);
            } catch (\Throwable $e) {
                throw new EhImportException('Falha de rede ao buscar no E-Hentai: '.$e->getMessage());
            }

            if (! $response->ok()) {
                throw new EhImportException('O E-Hentai retornou HTTP '.$response->status().' para a busca.');
            }

            return $response->body();
        });

        return $this->parseResults($html) + ['url' => $url];
    }

    /**
     * @return array{items: array<int, array{gid: int, token: string, title: string, thumb: string|null, category: string|null, pages: int|null, posted: string|null, uploader: string|null, source_url: string}>, next: int|null, prev: int|null, found: int|null}
     */
    public function parseResults(string $html): array
    {
        $items = [];
        $seen = [];

        foreach (preg_split('#<tr\b#i', $html) ?: [] as $row) {
            if (preg_match('#/g/(\d+)/([a-f0-9]{10})#i', $row, $gallery) !== 1) {
                continue;
            }

            $gid = (int) $gallery[1];
            if (isset($seen[$gid])) {
                continue;
            }
            $seen[$gid] = true;
            $token = strtolower($gallery[2]);

            $items[] = [
                'gid' => $gid,
                'token' => $token,
                'title' => $this->titleFromRow($row, $gid),
                'thumb' => $this->thumbFromRow($row),
                'category' => $this->matchText('#<div class="cn [^"]*"[^>]*>([^<]+)</div>#i', $row),
                'pages' => $this->pagesFromRow($row),
                'posted' => $this->matchText('#(\d{4}-\d{2}-\d{2} \d{2}:\d{2})#', $row),
                'uploader' => $this->matchText('#/uploader/[^"]*"[^>]*>([^<]+)<#i', $row),
                'source_url' => 'https://e-hentai.org/g/'.$gid.'/'.$token.'/',
            ];
        }

        return [
            'items' => $items,
            'next' => $this->cursorFromPage($html, 'next'),
            'prev' => $this->cursorFromPage($html, 'prev'),
            'found' => $this->matchNumber('#Found about ([\d,]+) results#i', $html),
        ];
    }

    protected function titleFromRow(string $row, int $gid): string
    {
        $title = $this->matchText('#<div class="glink">(.*?)</div>#is', $row);

        if ($title === null) {
            $title = $this->matchText(
                '#<a[^>]+href="[^"]*/g/'.$gid.'/[a-f0-9]{10}/"[^>]*>(.*?)</a>#is',
                $row
            );
        }

        return $title ?? 'Galeria '.$gid;
    }

    protected function thumbFromRow(string $row): ?string
    {
        foreach (['#<img[^>]+data-src="([^"]+)"#i', '#<img[^>]+src="([^"]+)"#i'] as $pattern) {
            if (preg_match($pattern, $row, $m) !== 1) {
                continue;
            }
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if (preg_match('#^https?://#i', $url) === 1) {
                return $url;
            }
        }

        return null;
    }

    protected function pagesFromRow(string $row): ?int
    {
        return preg_match('#(\d[\d,]*)\s+pages#i', $row, $m) === 1
            ? (int) str_replace(',', '', $m[1])
            : null;
    }

    /**
     * Extract the next/prev cursor from the page's JS variables, falling
     * back to the navigation anchors when the script block changes.
     */
    protected function cursorFromPage(string $html, string $direction): ?int
    {
        if (preg_match('#var '.$direction.'url="([^"]*)"#i', $html, $m) === 1) {
            $cursor = $this->cursorFromUrl($m[1], $direction);
            if ($cursor !== null) {
                return $cursor;
            }
        }

        foreach ([
            '#<a[^>]+id="u'.$direction.'"[^>]*href="([^"]+)"#i',
            '#<a[^>]+href="([^"]+)"[^>]*id="u'.$direction.'"#i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m) === 1) {
                $cursor = $this->cursorFromUrl($m[1], $direction);
                if ($cursor !== null) {
                    return $cursor;
                }
            }
        }

        return null;
    }

    protected function cursorFromUrl(?string $url, string $direction): ?int
    {
        if ($url === null || $url === '') {
            return null;
        }

        $query = parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY) ?: '';
        parse_str($query, $params);

        return $this->positiveInt($params[$direction] ?? null);
    }

    protected function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    protected function matchText(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $m) !== 1) {
            return null;
        }

        $text = preg_replace('#<[^>]+>#', ' ', $m[1]) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    protected function matchNumber(string $pattern, string $subject): ?int
    {
        return preg_match($pattern, $subject, $m) === 1
            ? (int) str_replace(',', '', $m[1])
            : null;
    }
}
