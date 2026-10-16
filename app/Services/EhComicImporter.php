<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Full-comic import from E-Hentai: gallery metadata (via the gdata API)
 * plus every full-size image, scraped server-side.
 *
 * Galleries list thumbs linking to image pages (/s/<token>/<gid>-<page>);
 * each image page carries the full-size <img id="img" src="...">. Pages
 * are enumerated up front so the progress view can download them a few
 * at a time without hammering the rate limiter.
 */
class EhComicImporter
{
    public function __construct(protected EhTagLookupService $lookup) {}

    /**
     * Fetch everything the import form needs: metadata + ordered
     * image-page URLs.
     *
     * @return array{gid: int, token: string, title: string, title_jpn: string|null, artist: string, tags: array<int, string>, category: string|null, filecount: int, thumb: string|null, source_url: string, page_urls: array<int, string>, truncated: bool}
     *
     * @throws EhImportException
     */
    public function previewFromUrl(string $input): array
    {
        $gallery = $this->lookup->parseGalleryUrl($input);
        if ($gallery === null && ($page = $this->lookup->parsePageUrl($input)) !== null) {
            $token = $this->lookup->resolvePageToken($page['page_token'], $page['gid'], $page['page']);
            if ($token === null) {
                throw new EhImportException('Não foi possível identificar a galeria a partir dessa URL.');
            }
            $gallery = ['gid' => $page['gid'], 'token' => $token];
        }
        if ($gallery === null) {
            throw new EhImportException('URL inválida. Cole a URL da galeria (…/g/1234567/abcd…/) ou de uma página dela.');
        }

        $meta = $this->lookup->fetchMetadata([$gallery])[0] ?? [];
        if (isset($meta['error'])) {
            throw new EhImportException('E-Hentai respondeu: '.(string) $meta['error']);
        }
        if (! isset($meta['title']) && ! isset($meta['gid'])) {
            throw new EhImportException('A API do E-Hentai não retornou essa galeria. Confira a URL.');
        }

        $tags = is_array($meta['tags'] ?? null) ? array_values($meta['tags']) : [];
        $filecount = max(0, (int) ($meta['filecount'] ?? 0));

        $pageUrls = $this->enumeratePageUrls($gallery['gid'], $gallery['token'], $filecount);
        if ($pageUrls === []) {
            throw new EhImportException('Nenhuma página encontrada na galeria. Ela pode estar removida ou exigir login.');
        }

        $truncated = $filecount > 0 && count($pageUrls) < $filecount;
        $pageUrls = array_values(array_slice($pageUrls, 0, max(1, (int) config('ehimport.max_pages', 600))));
        $truncated = $truncated || ($filecount > 0 && count($pageUrls) < $filecount);

        return [
            'gid' => $gallery['gid'],
            'token' => $gallery['token'],
            'title' => (string) ($meta['title'] ?? 'Galeria '.$gallery['gid']),
            'title_jpn' => isset($meta['title_jpn']) ? (string) $meta['title_jpn'] : null,
            'artist' => $this->artistNames($tags),
            'tags' => $tags,
            'category' => isset($meta['category']) ? (string) $meta['category'] : null,
            'filecount' => $filecount,
            'thumb' => isset($meta['thumb']) ? (string) $meta['thumb'] : null,
            'source_url' => $this->galleryUrl($gallery['gid'], $gallery['token']),
            'page_urls' => $pageUrls,
            'truncated' => $truncated,
        ];
    }

    /**
     * Artist tag values ("artist:name" → "name"), comma-joined.
     *
     * @param  array<int, string>  $tags
     */
    public function artistNames(array $tags): string
    {
        $artists = [];
        foreach (EhTagLookupService::normalizeTags($tags) as $tag) {
            if ($tag['namespace'] === 'artist') {
                $artists[] = $tag['name'];
            }
        }

        return implode(', ', array_values(array_unique($artists)));
    }

    /**
     * Walk the gallery index pages (?p=0, ?p=1, …) collecting image-page
     * URLs until the filecount is covered, a page adds nothing new, or
     * the index cap is hit.
     *
     * @return array<int, string> ordered by page number
     *
     * @throws EhImportException
     */
    public function enumeratePageUrls(int $gid, string $token, int $filecount): array
    {
        $found = [];
        $maxIndex = max(1, (int) config('ehimport.max_index_pages', 25));

        for ($index = 0; $index < $maxIndex; $index++) {
            $html = $this->fetchHtml($this->galleryUrl($gid, $token, $index));

            $new = 0;
            foreach ($this->extractPageLinks($html, $gid) as $number => $url) {
                if (! isset($found[$number])) {
                    $found[$number] = $url;
                    $new++;
                }
            }

            if ($filecount > 0 && count($found) >= $filecount) {
                break;
            }
            if ($new === 0) {
                break;
            }
            $this->politeDelay();
        }

        if ($found === []) {
            return [];
        }

        ksort($found, SORT_NUMERIC);

        return array_values($found);
    }

    /**
     * Resolve one image page (/s/…) to its full-size image URL.
     *
     * @throws EhImportException
     */
    public function resolveImageUrl(string $pageUrl): string
    {
        $html = $this->fetchHtml($pageUrl);

        // Attribute order varies; isolate the #img tag first, then its src.
        if (preg_match('#<img\b[^>]*\bid="img"[^>]*>#i', $html, $m) !== 1
            || preg_match('#\bsrc="([^"]+)"#i', $m[0], $src) !== 1) {
            throw new EhImportException('Página sem imagem original: '.$pageUrl);
        }

        $this->politeDelay();

        return html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Download one image into memory, validated. When the direct fetch
     * fails (e.g. hosts blocking non-standard hath ports) and an image
     * proxy is configured, retry once through it — the original error
     * is reported if the proxy fails too.
     *
     * @return array{bytes: string, extension: string}
     *
     * @throws EhImportException
     */
    public function downloadImage(string $imageUrl): array
    {
        try {
            return $this->fetchImageBytes($imageUrl);
        } catch (EhImportException $original) {
            $proxy = trim((string) config('ehimport.image_proxy', ''));
            if ($proxy === '') {
                throw $original;
            }

            try {
                return $this->fetchImageBytes($this->proxiedUrl($proxy, $imageUrl), $imageUrl);
            } catch (EhImportException $proxyError) {
                throw new EhImportException($original->getMessage().' [proxy também falhou: '.$proxyError->getMessage().']');
            }
        }
    }

    /**
     * @return array{bytes: string, extension: string}
     *
     * @throws EhImportException
     */
    protected function fetchImageBytes(string $imageUrl, ?string $label = null): array
    {
        $label ??= $imageUrl;

        // Extra pause before the heavy call (plus jitter below).
        $this->politeDelay(max(0, (int) config('ehimport.image_delay_ms', 3000)));

        try {
            $response = Http::connectTimeout(10)
                ->timeout((int) config('ehimport.image_timeout', 45))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Laravel comic importer)', 'Referer' => 'https://e-hentai.org/'])
                ->get($imageUrl);
        } catch (\Throwable $e) {
            throw new EhImportException('Falha de rede ao baixar '.$label.': '.$e->getMessage());
        }

        if (! $response->ok()) {
            throw new EhImportException('Download retornou HTTP '.$response->status().': '.$label);
        }

        $bytes = $response->body();
        $maxBytes = max(1, (int) config('ehimport.max_image_mb', 20)) * 1024 * 1024;
        if ($bytes === '' || strlen($bytes) > $maxBytes) {
            throw new EhImportException('Imagem vazia ou grande demais: '.$label);
        }
        if (@getimagesizefromstring($bytes) === false) {
            throw new EhImportException('O arquivo baixado não é uma imagem válida: '.$label);
        }

        $this->politeDelay();

        return ['bytes' => $bytes, 'extension' => $this->extensionFor($response->header('Content-Type'), $label)];
    }

    /**
     * Route an image through a relay on standard ports (e.g. wsrv.nl),
     * for hosts whose firewall blocks the hath network's random ports.
     * The proxy base must end with the url parameter, e.g.
     * https://images.weserv.nl/?url=
     */
    public function proxiedUrl(string $proxy, string $imageUrl): string
    {
        $stripped = (string) preg_replace('#^https?://#i', '', trim($imageUrl));

        return rtrim($proxy).rawurlencode($stripped);
    }

    /**
     * @return array<int, string> page number → image-page URL
     */
    public function extractPageLinks(string $html, int $gid): array
    {
        $links = [];
        if (preg_match_all('#/s/([a-f0-9]{10})/'.preg_quote((string) $gid, '#').'-(\d+)#i', $html, $m, PREG_SET_ORDER) !== false) {
            foreach ($m as $match) {
                $links[(int) $match[2]] = $this->base()."s/{$match[1]}/{$gid}-".((int) $match[2]);
            }
        }

        return $links;
    }

    /**
     * @throws EhImportException
     */
    protected function fetchHtml(string $url): string
    {
        try {
            $response = Http::connectTimeout(10)
                ->timeout((int) config('ehimport.html_timeout', 15))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Laravel comic importer)'])
                ->withCookies($this->authCookies(), '.e-hentai.org')
                ->get($url);
        } catch (\Throwable $e) {
            throw new EhImportException('Falha de rede ao ler '.$url.': '.$e->getMessage());
        }

        if (! $response->ok()) {
            throw new EhImportException('E-Hentai retornou HTTP '.$response->status().' ao ler '.$url);
        }

        return $response->body();
    }

    protected function galleryUrl(int $gid, string $token, int $index = 0): string
    {
        $url = $this->base()."g/{$gid}/{$token}/";

        return $index > 0 ? $url."?p={$index}" : $url;
    }

    protected function base(): string
    {
        return config('taglookup.use_exhentai')
            ? 'https://exhentai.org/'
            : 'https://e-hentai.org/';
    }

    /**
     * @return array<string, string>
     */
    protected function authCookies(): array
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

    protected function extensionFor(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $mime = strtolower(trim(explode(';', (string) $contentType)[0]));
        if (isset($map[$mime])) {
            return $map[$mime];
        }

        $ext = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : 'jpg';
    }

    protected function politeDelay(?int $baseMs = null): void
    {
        $base = $baseMs ?? (int) config('ehimport.delay_ms', 1200);
        $jitter = (int) config('ehimport.delay_jitter_ms', 800);
        $ms = $base + ($jitter > 0 ? random_int(0, $jitter) : 0);
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
