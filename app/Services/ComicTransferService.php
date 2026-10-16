<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\ComicTransfer;
use App\Models\Page;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Sending side of server-to-server transfers: talks to the remote
 * transfer API (config/transfer.php) with the shared-secret token.
 * Page uploads are idempotent remotely (updateOrCreate by number),
 * so interrupted transfers safely resume from page one.
 */
class ComicTransferService
{
    /**
     * @throws TransferException
     */
    public function createRemote(Comic $comic): int
    {
        $response = $this->client()->post($this->base().'/transfer/comics', [
            'title' => $comic->title,
            'author' => $comic->author,
            'description' => $comic->description,
            'slug' => $comic->slug,
            'published_at' => $comic->published_at?->toIso8601String(),
            'tags' => $comic->tags->pluck('name')->values()->all(),
        ]);

        if (! $response->ok() || ! is_numeric($response->json('comic_id'))) {
            throw new TransferException('Remoto recusou a criação ('.$response->status().'): '.mb_substr((string) $response->body(), 0, 200));
        }

        return (int) $response->json('comic_id');
    }

    /**
     * @throws TransferException
     */
    public function pushPage(ComicTransfer $transfer, Page $page, string $fullPath): int
    {
        $response = $this->client()->attach(
            'image',
            (string) file_get_contents($fullPath),
            basename($fullPath)
        )->post($this->base()."/transfer/comics/{$transfer->remote_comic_id}/pages", [
            'page_number' => $page->page_number,
        ]);

        if (! $response->ok()) {
            throw new TransferException('Remoto recusou a página '.$page->page_number.' ('.$response->status().'): '.mb_substr((string) $response->body(), 0, 200));
        }

        return (int) ($response->json('page_number') ?? $page->page_number);
    }

    /**
     * Page numbers already stored on the remote comic.
     *
     * @return array<int, int>
     *
     * @throws TransferException
     */
    public function remotePages(ComicTransfer $transfer): array
    {
        $response = $this->client()->get($this->base()."/transfer/comics/{$transfer->remote_comic_id}/pages");

        if (! $response->ok() || ! is_array($response->json('pages'))) {
            throw new TransferException('Remoto não listou as páginas ('.$response->status().').');
        }

        return array_map('intval', $response->json('pages'));
    }

    /**
     * @return array{url: string|null, pages: int}
     *
     * @throws TransferException
     */
    public function finalize(ComicTransfer $transfer): array
    {
        $response = $this->client()->post($this->base()."/transfer/comics/{$transfer->remote_comic_id}/finalize");

        if (! $response->ok()) {
            throw new TransferException('Remoto recusou a finalização ('.$response->status().').');
        }

        return [
            'url' => is_string($response->json('url')) ? $response->json('url') : null,
            'pages' => (int) ($response->json('pages') ?? 0),
        ];
    }

    /**
     * @throws TransferException
     */
    protected function client(): PendingRequest
    {
        $url = trim((string) config('transfer.remote_url'));
        $token = trim((string) config('transfer.remote_token'));
        if ($url === '' || $token === '') {
            throw new TransferException('Remoto não configurado (TRANSFER_REMOTE_URL / TRANSFER_REMOTE_TOKEN).');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout((int) config('transfer.timeout', 60));
    }

    protected function base(): string
    {
        return rtrim(trim((string) config('transfer.remote_url')), '/').'/api';
    }
}
