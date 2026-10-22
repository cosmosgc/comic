<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\EhImport;
use App\Models\EhImportPage;
use App\Models\EhImportQueueItem;
use App\Models\EhImportSetting;
use App\Models\Page;
use App\Models\Tag;
use App\Services\ComicDuplicateFinder;
use App\Services\EhComicImporter;
use App\Services\EhGallerySearch;
use App\Services\EhImportException;
use App\Services\EhTagLookupService;
use App\Services\EhTitleCleaner;
use App\Support\ComicDiskUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Full-comic import from E-Hentai (admin only).
 *
 * Flow: paste a gallery URL → preview (editable title, artist, tags,
 * schedule) → create the scheduled comic → poll the chunk endpoint
 * until every page is downloaded. Each poll downloads a few images so
 * slow shared hosts never hit execution limits.
 *
 * Tracking lives in the database (eh_imports + eh_import_pages, FK'd
 * to the comic with cascade): every queue item carries its final page
 * number, so retries and redownloads always land in gallery order
 * regardless of completion order — and survive restarts, deploys and
 * cache expiry.
 */
class AdminImportController extends Controller
{
    public function __construct(
        protected EhComicImporter $importer,
        protected ComicDuplicateFinder $duplicates,
        protected EhTagLookupService $lookup,
        protected EhGallerySearch $search,
        protected EhTitleCleaner $titleCleaner,
    ) {}

    /**
     * Step 1: URL form + upcoming schedule queue + tuning.
     */
    public function show(): View
    {
        return view('admin.import', [
            'stage' => 'form',
            'defaultDate' => Comic::defaultScheduledDate()->format('Y-m-d\\TH:i'),
            'scheduledDateCounts' => Comic::scheduledDateCounts(),
            'queue' => Comic::scheduled()->with('ehImport:id,comic_id,total_pages')->withCount('pages')
                ->orderBy('published_at')->orderBy('id')->limit(8)->get(['id', 'title', 'published_at']),
            'settingDefs' => EhImportSetting::definitions(),
            'importQueue' => $this->queuePayload(),
            // Comics already imported from E-Hentai: the only ones with
            // page URLs to (re)download, so the only valid "add existing".
            'queueComics' => Comic::query()
                ->whereHas('ehImport', fn ($q) => $q->whereNotNull('gid'))
                ->orderBy('title')
                ->limit(1000)
                ->get(['id', 'title']),
        ]);
    }

    /**
     * Gallery search with cursor pagination (free text or a pasted
     * E-Hentai search URL) used to fill the bulk import queue.
     */
    public function search(Request $request): View|RedirectResponse
    {
        $input = trim((string) $request->query('q', ''));

        if ($input === '') {
            return redirect()->route('admin.import')
                ->withFragment('import-search')
                ->with('error', 'Digite um termo de busca ou cole a URL de uma busca do E-Hentai.');
        }

        // A gallery (or image page) URL belongs to the single-gallery form.
        if ($this->lookup->parseGalleryUrl($input) !== null || $this->lookup->parsePageUrl($input) !== null) {
            return redirect()->route('admin.import')
                ->withInput(['url' => $input])
                ->withFragment('import-gallery')
                ->with('error', 'Essa URL é de uma galeria — ela já foi preenchida em “Buscar galeria”.');
        }

        $parsed = $this->search->parseSearchInput($input);
        $query = $parsed['query'];
        $next = $request->filled('next') ? (int) $request->query('next') : $parsed['next'];
        $prev = $request->filled('prev') ? (int) $request->query('prev') : $parsed['prev'];
        $page = max(1, (int) $request->query('page', 1));

        try {
            $result = $this->search->search($query, $next, $prev);
        } catch (EhImportException $e) {
            return view('admin.import', [
                'stage' => 'search',
                'searchQuery' => $query,
                'searchError' => $e->getMessage(),
                'results' => [],
                'nextCursor' => null,
                'prevCursor' => null,
                'foundCount' => null,
                'page' => $page,
                'importedGids' => [],
                'queuedGids' => [],
            ]);
        }

        $gids = array_map(fn (array $item): int => $item['gid'], $result['items']);

        $imported = EhImport::query()
            ->whereIn('gid', $gids)
            ->with('comic:id,title')
            ->get()
            ->mapWithKeys(fn (EhImport $import) => [
                (int) $import->gid => [
                    'comic_id' => (int) $import->comic_id,
                    'title' => (string) ($import->comic?->title ?? ''),
                ],
            ])
            ->all();

        $queued = EhImportQueueItem::query()
            ->whereIn('gid', $gids)
            ->pluck('status', 'gid')
            ->mapWithKeys(fn ($status, $gid) => [(int) $gid => (string) $status])
            ->all();

        return view('admin.import', [
            'stage' => 'search',
            'searchQuery' => $query,
            'searchError' => null,
            'results' => $result['items'],
            'nextCursor' => $result['next'],
            'prevCursor' => $result['prev'],
            'foundCount' => $result['found'],
            'page' => $page,
            'importedGids' => $imported,
            'queuedGids' => $queued,
        ]);
    }

    /**
     * Add the galleries selected on the search screen to the queue.
     * Metadata is re-read from the API so titles/thumbs are canonical.
     */
    public function storeQueue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*' => ['required', 'string', 'regex:/^\d+:[a-f0-9]{10}$/i'],
        ]);

        $pairs = [];
        foreach ($validated['items'] as $raw) {
            [$gid, $token] = explode(':', (string) $raw, 2);
            $pairs[(int) $gid] = strtolower($token);
        }

        $added = 0;
        $skipped = 0;
        $toAdd = [];

        foreach ($pairs as $gid => $token) {
            if ($this->duplicates->findByGallery($gid) !== null) {
                $skipped++;

                continue;
            }

            $existing = EhImportQueueItem::query()->where('gid', $gid)->first();
            if ($existing !== null && in_array($existing->status, [
                EhImportQueueItem::STATUS_PENDING,
                EhImportQueueItem::STATUS_PROCESSING,
                EhImportQueueItem::STATUS_DONE,
                EhImportQueueItem::STATUS_SKIPPED,
            ], true)) {
                $skipped++;

                continue;
            }

            $toAdd[$gid] = $token;
        }

        $metadata = [];
        $list = [];
        foreach ($toAdd as $gid => $token) {
            $list[] = ['gid' => $gid, 'token' => $token];
        }

        // The API accepts at most 25 galleries per call. Without metadata
        // the queue still works: the item falls back to a placeholder title
        // and the real data is read when the gallery is processed.
        foreach (array_chunk($list, 25) as $batch) {
            try {
                $batchMetadata = $this->lookup->fetchMetadata($batch);
            } catch (\Throwable) {
                $batchMetadata = [];
            }

            foreach ($batchMetadata as $meta) {
                if (isset($meta['gid'])) {
                    $metadata[(int) $meta['gid']] = $meta;
                }
            }
        }

        foreach ($toAdd as $gid => $token) {
            $meta = $metadata[$gid] ?? [];
            $title = $this->titleCleaner->clean(trim((string) ($meta['title'] ?? '')));
            $thumb = trim((string) ($meta['thumb'] ?? ''));

            EhImportQueueItem::updateOrCreate(
                ['gid' => $gid],
                [
                    'token' => $token,
                    'title' => mb_substr($title !== '' ? $title : 'Galeria '.$gid, 0, 255),
                    'thumb' => $thumb !== '' ? mb_substr($thumb, 0, 500) : null,
                    'source_url' => $this->importer->sourceUrl($gid, $token),
                    'status' => EhImportQueueItem::STATUS_PENDING,
                    'position' => $this->nextQueuePosition(),
                    'redownload' => false,
                    'comic_id' => null,
                    'error' => null,
                    'processed_at' => null,
                ]
            );
            $added++;
        }

        $message = $added.' galeria(s) adicionada(s) à fila.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' ignorada(s) (já importada(s) ou já na fila).';
        }

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with($added > 0 ? 'success' : 'error', $message);
    }

    /**
     * Add a single gallery to the end of the queue from a pasted URL
     * (gallery or one of its image pages), skipping the search step.
     */
    public function addQueueItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:500'],
        ]);
        $input = trim($validated['url']);

        $gallery = $this->lookup->parseGalleryUrl($input);
        if ($gallery === null) {
            $page = $this->lookup->parsePageUrl($input);
            $token = $page !== null
                ? $this->lookup->resolvePageToken($page['page_token'], $page['gid'], $page['page'])
                : null;
            if ($page === null || $token === null) {
                return redirect()->route('admin.import')
                    ->withFragment('import-queue')
                    ->with('error', 'Cole a URL de uma galeria ou de uma página dela no E-Hentai.');
            }
            $gallery = ['gid' => $page['gid'], 'token' => $token];
        }

        $gid = (int) $gallery['gid'];
        $token = strtolower($gallery['token']);

        $existingComic = $this->duplicates->findByGallery($gid);
        if ($existingComic !== null) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Esta galeria já foi importada como “'.$existingComic->title.'” — use “Adicionar comics já no site” para baixar as páginas de novo.');
        }

        if (EhImportQueueItem::query()->where('gid', $gid)->exists()) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Esta galeria já está na fila.');
        }

        $meta = $this->queueMetadata($gid, $token);
        $title = $this->titleCleaner->clean(trim((string) ($meta['title'] ?? '')));
        $thumb = trim((string) ($meta['thumb'] ?? ''));

        EhImportQueueItem::create([
            'gid' => $gid,
            'token' => $token,
            'title' => mb_substr($title !== '' ? $title : 'Galeria '.$gid, 0, 255),
            'thumb' => $thumb !== '' ? mb_substr($thumb, 0, 500) : null,
            'source_url' => $this->importer->sourceUrl($gid, $token),
            'status' => EhImportQueueItem::STATUS_PENDING,
            'position' => $this->nextQueuePosition(),
            'redownload' => false,
        ]);

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with('success', 'Galeria “'.($title !== '' ? $title : $gid).'” adicionada ao fim da fila.');
    }

    /**
     * Put a comic that is already on the site back in the queue: it then
     * downloads its pending/failed pages, or the whole gallery again when
     * "Baixar todas as páginas de novo" is checked.
     */
    public function addQueueComic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'comic_id' => ['required', 'integer', 'exists:comics,id'],
            'redownload' => ['nullable', 'boolean'],
        ]);

        $comic = Comic::with('ehImport')->findOrFail((int) $validated['comic_id']);
        $import = $comic->ehImport;

        if ($import === null || $import->gid === null || trim((string) $import->token) === '') {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Este comic não tem uma importação do E-Hentai para baixar.');
        }

        if (EhImportQueueItem::query()->where('gid', (int) $import->gid)->exists()) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Este comic já está na fila.');
        }

        $redownload = $request->boolean('redownload');
        if (! $redownload && ! $import->pages()
            ->whereIn('status', [
                EhImportPage::STATUS_PENDING,
                EhImportPage::STATUS_DOWNLOADING,
                EhImportPage::STATUS_FAILED,
            ])->exists()) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Todas as páginas deste comic já estão baixadas — marque “Baixar todas as páginas de novo” para baixar outra vez.');
        }

        if (! $redownload) {
            // Same as "Tentar de novo" per item: failed pages get a fresh
            // round of attempts instead of being skipped.
            $import->pages()
                ->where('status', EhImportPage::STATUS_FAILED)
                ->update(['status' => EhImportPage::STATUS_PENDING, 'attempts' => 0, 'error' => null]);
        }

        EhImportQueueItem::create([
            'gid' => (int) $import->gid,
            'token' => strtolower((string) $import->token),
            'title' => mb_substr($comic->title, 0, 255),
            'thumb' => $comic->image_path !== null ? mb_substr(asset('storage/'.$comic->image_path), 0, 500) : null,
            'source_url' => mb_substr((string) ($import->source_url ?: $this->importer->sourceUrl((int) $import->gid, (string) $import->token)), 0, 500),
            'status' => EhImportQueueItem::STATUS_PENDING,
            'position' => $this->nextQueuePosition(),
            'redownload' => $redownload,
            'comic_id' => $comic->id,
        ]);

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with('success', '“'.$comic->title.'” adicionado ao fim da fila'.($redownload ? ' para baixar tudo de novo.' : '.'));
    }

    /**
     * Persist the order chosen by dragging the queue rows.
     */
    public function reorderQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $existing = EhImportQueueItem::query()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sent = array_values(array_intersect(
            array_map('intval', $validated['ids']),
            $existing
        ));
        $order = array_values(array_merge($sent, array_diff($existing, $sent)));

        DB::transaction(function () use ($order): void {
            foreach ($order as $index => $id) {
                EhImportQueueItem::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return response()->json(['ok' => true, 'order' => $order]);
    }

    /**
     * Process the next queued gallery: create its scheduled comic with
     * the gallery defaults, then download one chunk of pages. The admin
     * page calls this repeatedly, so a closed tab simply pauses the queue.
     */
    public function processQueue(): JsonResponse
    {
        $lock = Cache::lock('ehimport:queue:lock', 180);
        if (! $lock->get()) {
            return response()->json(['message' => 'Outra requisição já está processando a fila. Aguarde.'], 409);
        }

        try {
            $item = EhImportQueueItem::query()
                ->whereIn('status', [EhImportQueueItem::STATUS_PROCESSING, EhImportQueueItem::STATUS_PENDING])
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [EhImportQueueItem::STATUS_PROCESSING])
                ->orderBy('position')
                ->orderBy('id')
                ->first();

            if ($item === null) {
                return response()->json(['done' => true, 'queue' => $this->queuePayload()]);
            }

            $comic = $item->comic_id !== null ? Comic::find($item->comic_id) : null;

            // A comic added with "Baixar todas as páginas de novo": wipe
            // the old pages once, then keep downloading normally.
            if ($comic !== null && $item->redownload) {
                try {
                    $this->resetComicImport(
                        $comic,
                        (string) ($item->source_url ?: $comic->ehImport?->source_url)
                    );
                    $item->update(['redownload' => false]);
                } catch (\Throwable $e) {
                    $item->update([
                        'status' => EhImportQueueItem::STATUS_FAILED,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                        'processed_at' => now(),
                    ]);

                    return response()->json([
                        'failed' => true,
                        'message' => $e->getMessage(),
                        'queue' => $this->queuePayload(),
                    ]);
                }
            }

            if ($comic === null) {
                if ($item->comic_id !== null) {
                    $item->update(['comic_id' => null]);
                }
                $item->update([
                    'status' => EhImportQueueItem::STATUS_PROCESSING,
                    'error' => null,
                    'processed_at' => null,
                ]);

                try {
                    $comic = $this->createComicFromQueueItem($item->fresh());
                } catch (\Throwable $e) {
                    $item->update([
                        'status' => EhImportQueueItem::STATUS_FAILED,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                        'processed_at' => now(),
                    ]);

                    return response()->json([
                        'failed' => true,
                        'message' => $e->getMessage(),
                        'queue' => $this->queuePayload(),
                    ]);
                }

                if ($comic === null) {
                    // The gallery was already imported: item marked skipped.
                    return response()->json(['skipped' => true, 'queue' => $this->queuePayload()]);
                }

                $item->update(['comic_id' => $comic->id]);
            } elseif ($item->status === EhImportQueueItem::STATUS_PENDING) {
                $item->update(['status' => EhImportQueueItem::STATUS_PROCESSING, 'error' => null]);
            }

            $import = $comic->fresh()->ehImport;
            if ($import === null) {
                $item->update([
                    'status' => EhImportQueueItem::STATUS_FAILED,
                    'error' => 'Comic sem fila de páginas — abra a importação e use “Baixar tudo de novo”.',
                    'processed_at' => now(),
                ]);

                return response()->json([
                    'failed' => true,
                    'message' => 'Comic sem fila de páginas.',
                    'queue' => $this->queuePayload(),
                ]);
            }

            $chunk = $this->downloadChunk($import);
            if ($chunk['busy']) {
                return response()->json(['message' => 'Outra requisição está baixando este comic. Aguarde.'], 409);
            }

            $payload = $this->finishImport($comic->fresh());
            $failures = count($payload['failed']);

            if ($payload['complete']) {
                $item->update([
                    'status' => EhImportQueueItem::STATUS_DONE,
                    'error' => $failures > 0
                        ? $failures.' página(s) falharam — abra a importação para tentar de novo.'
                        : null,
                    'processed_at' => now(),
                ]);
            }

            $queue = $this->queuePayload();
            $comicPayload = [
                'id' => $comic->id,
                'title' => $comic->title,
                'done' => $payload['done'],
                'total' => $payload['total'],
                'failed' => $failures,
                'complete' => $payload['complete'],
                'progress_url' => route('admin.import.progress', $comic),
            ];

            if (! $payload['complete'] && $chunk['limited'] && $chunk['processed'] === 0) {
                return response()->json(
                    [
                        'message' => 'Limite de downloads por minuto atingido — aguardando.',
                        'retry_after' => $chunk['retry_after'],
                        'comic' => $comicPayload,
                        'queue' => $queue,
                    ],
                    429,
                    ['Retry-After' => (string) $chunk['retry_after']]
                );
            }

            return response()->json([
                'done' => $queue['pending'] === 0 && $queue['processing'] === 0,
                'comic' => $comicPayload,
                'queue' => $queue,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Put a failed queue item back in line.
     */
    public function retryQueueItem(EhImportQueueItem $item): RedirectResponse
    {
        if ($item->status === EhImportQueueItem::STATUS_PROCESSING) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Este item está sendo processado agora.');
        }

        $comic = $item->comic_id !== null ? Comic::find($item->comic_id) : null;

        // A queue item can finish with failed pages: put them back in the
        // page queue so "Tentar de novo" actually retries the download.
        $import = $comic?->ehImport;
        if ($import !== null) {
            $import->pages()
                ->where('status', EhImportPage::STATUS_FAILED)
                ->update(['status' => EhImportPage::STATUS_PENDING, 'attempts' => 0, 'error' => null]);
            if ($import->isComplete()) {
                $import->update(['status' => EhImport::STATUS_DOWNLOADING]);
            }
        }

        $item->update([
            'status' => EhImportQueueItem::STATUS_PENDING,
            'comic_id' => $comic !== null ? $comic->id : null,
            'error' => null,
            'processed_at' => null,
        ]);

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with('success', 'Item voltou para a fila.');
    }

    /**
     * Drop a queue item that is not being downloaded.
     */
    public function removeQueueItem(EhImportQueueItem $item): RedirectResponse
    {
        if ($item->status === EhImportQueueItem::STATUS_PROCESSING) {
            return redirect()->route('admin.import')
                ->withFragment('import-queue')
                ->with('error', 'Este item está sendo processado agora — aguarde ou pause a fila.');
        }

        $item->delete();

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with('success', 'Item removido da fila.');
    }

    /**
     * Remove the finished (done/skipped) rows from the queue.
     */
    public function clearQueue(): RedirectResponse
    {
        $deleted = EhImportQueueItem::query()
            ->whereIn('status', [EhImportQueueItem::STATUS_DONE, EhImportQueueItem::STATUS_SKIPPED])
            ->delete();

        return redirect()->route('admin.import')
            ->withFragment('import-queue')
            ->with('success', $deleted.' item(ns) concluído(s) removido(s) da fila.');
    }

    /**
     * Step 2: fetch gallery metadata + page list, stash in cache,
     * render the editable form.
     */
    public function preview(Request $request): RedirectResponse|View
    {
        $request->validate(['url' => 'required|string|max:500']);

        try {
            $preview = $this->importer->previewFromUrl($request->input('url'));
        } catch (EhImportException $e) {
            return redirect()->route('admin.import')
                ->with('error', $e->getMessage())
                ->withInput();
        }

        $token = Str::random(32);
        Cache::put(
            "ehimport:preview:{$token}",
            ['meta' => $preview, 'page_urls' => $preview['page_urls']],
            now()->addSeconds(max(300, (int) config('ehimport.preview_ttl', 3600)))
        );

        $duplicates = $this->duplicates->find($preview['title'], $preview['gid'] ?? null);

        return view('admin.import', [
            'stage' => 'edit',
            'preview' => $preview,
            'previewToken' => $token,
            'defaultDate' => Comic::defaultScheduledDate()->format('Y-m-d\\TH:i'),
            'scheduledDateCounts' => Comic::scheduledDateCounts(),
            'suggestedDescription' => $this->suggestedDescription($preview),
            'duplicateExact' => $duplicates['exact'],
            'duplicateMatches' => $duplicates['matches'],
        ]);
    }

    /**
     * Step 3: create the scheduled comic, resolve tags, seed the page
     * queue, hand off to the progress view.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preview_token' => 'required|string|size:32',
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'tags' => 'nullable|string|max:20000',
            'published_at' => 'nullable|date',
        ]);

        $cached = Cache::get("ehimport:preview:{$validated['preview_token']}");
        if (! is_array($cached) || ($cached['page_urls'] ?? []) === []) {
            return redirect()->route('admin.import')
                ->with('error', 'A pré-visualização expirou. Busque a galeria novamente.');
        }

        $gid = (int) ($cached['meta']['gid'] ?? 0);
        $existing = $gid > 0 ? $this->duplicates->findByGallery($gid) : null;

        if ($existing !== null) {
            return redirect()->route('admin.import.progress', $existing)
                ->with('error', 'Esta galeria já foi importada como “'.$existing->title.'”. Use “Baixar tudo de novo” na página de progresso se precisar baixar as páginas outra vez.');
        }

        $comic = Comic::create([
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'slug' => Comic::generateUniqueSlug($validated['title']),
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
            'user_id' => Auth::id(),
        ]);

        $this->syncTagsFromFullNames($comic, $validated['tags'] ?? null);

        // Gallery identity lives nested under `meta` in the preview cache.
        $this->seedImport($comic, array_merge($cached['meta'] ?? [], ['page_urls' => $cached['page_urls']]));
        Cache::forget("ehimport:preview:{$validated['preview_token']}");

        return redirect()->route('admin.import.progress', $comic)
            ->with('success', 'Comic criado e agendado. Baixando as páginas…');
    }

    /**
     * Progress view: polls fetchPages until complete.
     */
    public function progress(Comic $comic): View
    {
        $comic = $comic->fresh()->load('transfer');
        $import = $comic->ehImport;

        return view('admin.import', [
            'stage' => 'progress',
            'comic' => $comic,
            'total' => $import?->total_pages ?? $comic->pages()->count(),
            'done' => $comic->pages()->count(),
            'failed' => $import ? $this->failedNumbers($import) : [],
            'errors' => $import ? $this->failedErrors($import) : [],
            'pageMap' => $this->pageMap($comic),
            'details' => $import ? $this->pageDetails($import) : [],
            'totalSize' => $import ? (int) $import->pages()->where('status', EhImportPage::STATUS_DONE)->sum('file_size') : 0,
            'logLines' => $this->logLines($import),
            'transferConfigured' => trim((string) config('transfer.remote_url')) !== ''
                && trim((string) config('transfer.remote_token')) !== '',
        ]);
    }

    /**
     * Browser-assisted upload: the admin's browser downloads the image
     * (it can reach hath ports the server firewall blocks) and posts
     * the bytes here. Counts as a normal downloaded page.
     */
    public function uploadPageFile(Request $request, Comic $comic, int $number): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:'.(max(1, (int) config('ehimport.max_image_mb', 20)) * 1024),
        ]);

        $import = $comic->ehImport;
        $row = $import?->pages()->where('page_number', $number)->first();
        abort_unless($row !== null, 404);

        $file = $request->file('image');
        $bytes = (string) file_get_contents($file->getRealPath());
        abort_if($bytes === '', 422, 'Arquivo vazio.');
        $this->storePage($comic, $bytes, $this->importer->extensionFor($file->getMimeType(), $file->getClientOriginalName()), $number);
        $row->update(['status' => EhImportPage::STATUS_DONE, 'error' => null, 'attempts' => 0, 'file_size' => strlen($bytes)]);
        $this->logEvent($import, "Página {$number} enviada pelo navegador (".round(strlen($bytes) / 1024).' KB).');

        return response()->json($this->finishImport($comic->fresh()));
    }

    /**
     * Download one stored page file to the admin's computer.
     */
    public function downloadPageFile(Comic $comic, int $number): BinaryFileResponse
    {
        $page = $comic->pages()->where('page_number', $number)->first();
        abort_unless($page !== null, 404);
        $full = public_path('storage/'.$page->image_path);
        abort_unless(is_file($full), 404);

        return response()->download($full, 'page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.'.pathinfo($full, PATHINFO_EXTENSION));
    }

    /**
     * Download the next chunk of pending pages (polled as JSON).
     */
    public function fetchPages(Comic $comic): JsonResponse
    {
        $import = $comic->ehImport;
        if ($import === null) {
            return response()->json($this->progressPayload($comic->fresh(), null, true));
        }

        $chunk = $this->downloadChunk($import);

        if ($chunk['busy']) {
            return response()->json(['message' => 'Outra requisição está baixando. Aguarde.'], 409);
        }

        if ($chunk['limited'] && $chunk['processed'] === 0) {
            return response()->json(
                ['message' => 'Limite de downloads por minuto atingido — aguardando.', 'retry_after' => $chunk['retry_after']],
                429,
                ['Retry-After' => (string) $chunk['retry_after']]
            );
        }

        return response()->json($this->finishImport($comic->fresh()));
    }

    /**
     * Download the next chunk of pending pages for one import.
     *
     * Shared by the per-comic progress view and the bulk queue. The lock
     * keeps two tabs (or a queue + progress page) from downloading the
     * same import at once.
     *
     * @return array{busy: bool, limited: bool, retry_after: int, processed: int}
     */
    protected function downloadChunk(EhImport $import): array
    {
        $lock = Cache::lock("ehimport:import:{$import->id}:lock", 120);
        if (! $lock->get()) {
            return ['busy' => true, 'limited' => false, 'retry_after' => 0, 'processed' => 0];
        }

        try {
            // Rows stuck in downloading belong to a dead poll: reclaim
            // them after 10 minutes so they don't stall the import.
            $import->pages()
                ->where('status', EhImportPage::STATUS_DOWNLOADING)
                ->where('updated_at', '<', now()->subMinutes(10))
                ->update(['status' => EhImportPage::STATUS_PENDING]);

            $chunk = $import->pages()
                ->where('status', EhImportPage::STATUS_PENDING)
                ->orderBy('page_number')
                ->limit(max(1, (int) config('ehimport.chunk', 3)))
                ->get();

            if ($chunk->isEmpty()) {
                return ['busy' => false, 'limited' => false, 'retry_after' => 0, 'processed' => 0];
            }

            $comic = $import->comic;
            if ($comic === null) {
                return ['busy' => false, 'limited' => false, 'retry_after' => 0, 'processed' => 0];
            }

            $import->update(['status' => EhImport::STATUS_DOWNLOADING]);

            // Hard rate ceiling (rolling minute, shared server-wide):
            // when hit, stop the chunk and answer 429 so the view backs
            // off instead of hammering into a block.
            $maxPerMinute = max(1, (int) config('ehimport.max_per_minute', 20));
            $limited = false;
            $processed = 0;
            $seen = [];
            $chunkSize = max(1, (int) config('ehimport.chunk', 3));
            while ($processed < $chunkSize) {
                // Skip rows already touched by this poll: a row that
                // fails stays pending and must wait for the next poll
                // (otherwise one poll burns all its attempts at once).
                $query = $import->pages()->where('status', EhImportPage::STATUS_PENDING)
                    ->orderBy('page_number');
                if ($seen !== []) {
                    $query->whereNotIn('id', $seen);
                }
                $row = $query->first();
                if ($row === null) {
                    break;
                }
                $seen[] = $row->id;
                if (RateLimiter::tooManyAttempts('ehimport:images', $maxPerMinute)) {
                    $limited = true;
                    break;
                }
                RateLimiter::hit('ehimport:images', 60);

                $row->update(['status' => EhImportPage::STATUS_DOWNLOADING]);
                try {
                    // Retries resolve a fresh URL: hath links carry an
                    // expiring keystamp, so a stored URL may be dead.
                    $imageUrl = ($row->image_url !== null && $row->attempts === 0)
                        ? $row->image_url
                        : $this->importer->resolveImageUrl($row->page_url);
                    if ($row->image_url !== $imageUrl) {
                        $row->update(['image_url' => $imageUrl]);
                    }
                    $file = $this->importer->downloadImage($imageUrl);
                    $this->storePage($comic, $file['bytes'], $file['extension'], $row->page_number);
                    $row->update(['status' => EhImportPage::STATUS_DONE, 'error' => null, 'file_size' => strlen($file['bytes'])]);
                } catch (EhImportException $e) {
                    $attempts = $row->attempts + 1;
                    $failed = $attempts >= max(1, (int) config('ehimport.max_attempts', 3));
                    $row->update([
                        'attempts' => $attempts,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                        'status' => $failed ? EhImportPage::STATUS_FAILED : EhImportPage::STATUS_PENDING,
                    ]);
                    if ($failed) {
                        $this->logEvent($import, "Página {$row->page_number} falhou após {$attempts} tentativas: ".mb_substr($e->getMessage(), 0, 200));
                    }
                }
                $processed++;
            }

            return [
                'busy' => false,
                'limited' => $limited,
                'retry_after' => $limited ? max(1, RateLimiter::availableIn('ehimport:images')) : 0,
                'processed' => $processed,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Re-queue every failed page with fresh attempts (polled by the
     * retry button).
     */
    public function retry(Comic $comic): JsonResponse
    {
        $import = $comic->ehImport;
        if ($import === null) {
            return response()->json(['message' => 'Nada para tentar de novo.'], 404);
        }

        $count = $import->pages()->where('status', EhImportPage::STATUS_FAILED)
            ->update(['status' => EhImportPage::STATUS_PENDING, 'attempts' => 0, 'error' => null]);
        if ($count === 0) {
            return response()->json(['message' => 'Nada para tentar de novo.'], 404);
        }
        if ($import->isComplete()) {
            $import->update(['status' => EhImport::STATUS_DOWNLOADING]);
        }
        $this->logEvent($import, "Retry geral: {$count} página(s) de volta na fila.");

        return response()->json(['ok' => true, 'pending' => $count]);
    }

    /**
     * Re-queue a single failed page (per-row retry button).
     */
    public function retryPage(Request $request, Comic $comic): JsonResponse
    {
        $request->validate(['number' => 'required|integer|min:1']);
        $number = (int) $request->input('number');

        if ($comic->pages()->where('page_number', $number)->exists()) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        $import = $comic->ehImport;
        $row = $import?->pages()->where('page_number', $number)->first();
        if ($row === null) {
            return response()->json(['message' => 'A fila expirou — use Baixar tudo de novo.'], 404);
        }
        if (! $row->isFailed()) {
            return response()->json(['message' => 'Página fora da lista de falhas.'], 404);
        }

        $row->update(['status' => EhImportPage::STATUS_PENDING, 'attempts' => 0, 'error' => null]);
        if ($import->isComplete()) {
            $import->update(['status' => EhImport::STATUS_DOWNLOADING]);
        }
        $this->logEvent($import, "Retry individual: página {$number} de volta na fila.");

        return response()->json(['ok' => true, 'pending' => $import->pages()->where('status', EhImportPage::STATUS_PENDING)->count()]);
    }

    /**
     * Wipe every page and download the whole gallery again in order.
     */
    public function redownload(Request $request, Comic $comic): RedirectResponse
    {
        $request->validate(['url' => 'required|string|max:500']);

        try {
            $import = $this->resetComicImport($comic, (string) $request->input('url'));
        } catch (EhImportException $e) {
            return redirect()->route('admin.import.progress', $comic)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.import.progress', $comic)
            ->with('success', $import->total_pages.' página(s) na fila — baixando de novo em ordem.');
    }

    /**
     * Save the tuning from the import view. Empty fields delete the row
     * (file default returns) and apply immediately to this process too.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $defs = EhImportSetting::definitions();
        $rules = [];
        foreach ($defs as $key => $def) {
            $rules[$key] = ($def['type'] ?? 'string') === 'int'
                ? "nullable|integer|min:{$def['min']}|max:{$def['max']}"
                : 'nullable|string|max:'.($def['maxlen'] ?? 500);
        }
        $validated = $request->validate($rules);

        foreach ($defs as $key => $def) {
            $raw = trim((string) ($validated[$key] ?? ''));
            if ($raw === '') {
                EhImportSetting::where('key', $key)->delete();

                continue;
            }
            EhImportSetting::updateOrCreate(['key' => $key], ['value' => $raw]);
            config(["ehimport.{$key}" => EhImportSetting::castValue($key, $raw)]);
        }

        return redirect()->route('admin.import')->with('success', 'Configurações de importação salvas.');
    }

    /**
     * Cancel an import: removes the comic, its pages, files and queue.
     */
    public function destroy(Comic $comic): RedirectResponse
    {
        // eh_imports + eh_import_pages go with the comic via cascade.
        $comic->pages()->delete();
        File::deleteDirectory(public_path("storage/comics/{$comic->id}"));
        $comic->delete();
        ComicDiskUsage::flush();

        return redirect()->route('admin.import')->with('success', 'Importação cancelada e comic removido.');
    }

    /**
     * Create the import record with one numbered row per gallery page:
     * gallery order defines the final page numbers up front.
     *
     * @param  array{source_url?: string, gid?: int, token?: string, page_urls: array<int, string>}  $preview
     */
    protected function seedImport(Comic $comic, array $preview): EhImport
    {
        $pageUrls = array_values($preview['page_urls']);
        $now = now();

        $import = EhImport::create([
            'comic_id' => $comic->id,
            'source_url' => $preview['source_url'] ?? null,
            'gid' => $preview['gid'] ?? null,
            'token' => $preview['token'] ?? null,
            'status' => EhImport::STATUS_PENDING,
            'total_pages' => count($pageUrls),
        ]);

        $rows = [];
        foreach ($pageUrls as $index => $url) {
            $rows[] = [
                'eh_import_id' => $import->id,
                'page_number' => $index + 1,
                'page_url' => $url,
                'status' => EhImportPage::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            EhImportPage::insert($rows);
        }

        $this->logEvent($import, "Import criado a partir de {$preview['source_url']} ({$import->total_pages} páginas).");

        return $import;
    }

    /**
     * Turn a queued gallery into a scheduled comic using the gallery
     * defaults (title, artist, tags, uploader description, next free
     * schedule date) and seed its page queue.
     *
     * @return Comic|null null when the gallery was already imported (item marked skipped)
     *
     * @throws EhImportException|\Throwable
     */
    protected function createComicFromQueueItem(EhImportQueueItem $item): ?Comic
    {
        $gid = (int) $item->gid;

        $existing = $this->duplicates->findByGallery($gid);
        if ($existing !== null) {
            $item->update([
                'status' => EhImportQueueItem::STATUS_SKIPPED,
                'comic_id' => $existing->id,
                'error' => 'Já importado como “'.$existing->title.'”.',
                'processed_at' => now(),
            ]);

            return null;
        }

        $preview = $this->importer->previewFromUrl($item->source_url);

        return DB::transaction(function () use ($preview): Comic {
            $comic = Comic::create([
                'title' => $preview['title'],
                'author' => $preview['artist'] !== '' ? $preview['artist'] : null,
                'description' => $this->suggestedDescription($preview),
                'slug' => Comic::generateUniqueSlug($preview['title']),
                'published_at' => Comic::defaultScheduledDate(),
                'user_id' => Auth::id(),
            ]);

            $this->syncTagsFromFullNames($comic, implode(', ', $preview['tags']));

            $import = $this->seedImport($comic, $preview);
            $this->logEvent($import, 'Importação criada pela fila de busca.');

            return $comic;
        });
    }

    /**
     * Queue state for the import view and the process endpoint.
     *
     * @return array{items: array<int, array{id: int, gid: int, title: string, thumb: string|null, status: string, redownload: bool, error: string|null, comic_id: int|null, done: int, total: int, progress_url: string|null, source_url: string}>, total: int, pending: int, processing: int, done: int, failed: int, skipped: int}
     */
    protected function queuePayload(): array
    {
        $items = EhImportQueueItem::query()
            ->with(['comic:id,title', 'comic.ehImport:id,comic_id,status,total_pages', 'comic.pages:id,comic_id'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $mapped = $items->map(function (EhImportQueueItem $item): array {
            $comic = $item->comic;

            return [
                'id' => (int) $item->id,
                'gid' => (int) $item->gid,
                'title' => $item->title,
                'thumb' => $item->thumb,
                'status' => $item->status,
                'redownload' => (bool) $item->redownload,
                'error' => $item->error,
                'comic_id' => $item->comic_id !== null ? (int) $item->comic_id : null,
                'done' => $comic !== null ? $comic->pages->count() : 0,
                'total' => (int) ($comic?->ehImport?->total_pages ?? 0),
                'progress_url' => $comic !== null ? route('admin.import.progress', $comic) : null,
                'source_url' => $item->source_url,
            ];
        })->all();

        return [
            'items' => $mapped,
            'total' => count($mapped),
            'pending' => $items->where('status', EhImportQueueItem::STATUS_PENDING)->count(),
            'processing' => $items->where('status', EhImportQueueItem::STATUS_PROCESSING)->count(),
            'done' => $items->where('status', EhImportQueueItem::STATUS_DONE)->count(),
            'failed' => $items->where('status', EhImportQueueItem::STATUS_FAILED)->count(),
            'skipped' => $items->where('status', EhImportQueueItem::STATUS_SKIPPED)->count(),
        ];
    }

    /**
     * Next free position at the end of the queue.
     */
    protected function nextQueuePosition(): int
    {
        return ((int) EhImportQueueItem::query()->max('position')) + 1;
    }

    /**
     * Best-effort gallery metadata for a manually added queue item.
     * Without it the item still works with a placeholder title.
     *
     * @return array<string, mixed>
     */
    protected function queueMetadata(int $gid, string $token): array
    {
        try {
            $metadata = $this->lookup->fetchMetadata([['gid' => $gid, 'token' => $token]]);
        } catch (\Throwable) {
            return [];
        }

        foreach ($metadata as $meta) {
            if (isset($meta['gid']) && (int) $meta['gid'] === $gid) {
                return $meta;
            }
        }

        return [];
    }

    /**
     * Wipe a comic's pages and seed a fresh import from its gallery URL.
     * Shared by "Baixar tudo de novo" and the queue's redownload flag.
     */
    protected function resetComicImport(Comic $comic, string $url): EhImport
    {
        $preview = $this->importer->previewFromUrl($url);

        $comic->pages()->delete();
        File::deleteDirectory(public_path("storage/comics/{$comic->id}/pages"));
        $comic->update(['image_path' => null]);
        $comic->ehImport?->delete();
        ComicDiskUsage::flush();

        $import = $this->seedImport($comic, $preview);
        $this->logEvent($import, 'Baixar tudo de novo: páginas antigas apagadas, fila zerada.');

        return $import;
    }

    /**
     * Settle an import after a chunk: complete (cover + status) when
     * nothing is pending or downloading anymore.
     *
     * @return array{done: int, total: int, failed: array<int, int>, errors: array<int, string>, pending: array<int, int>, pages: array<int, string>, details: array<int, array<string, mixed>>, total_size: int, log: array<int, string>, complete: bool}
     */
    protected function finishImport(Comic $comic): array
    {
        $import = $comic->ehImport;
        $open = $import
            ? $import->pages()->whereIn('status', [EhImportPage::STATUS_PENDING, EhImportPage::STATUS_DOWNLOADING])->exists()
            : false;

        $complete = ! $open;
        if ($complete && $import && ! $import->isComplete()) {
            $import->update(['status' => EhImport::STATUS_COMPLETE]);
            $this->ensureCover($comic);
            $failed = $import->pages()->where('status', EhImportPage::STATUS_FAILED)->count();
            $size = (int) $import->pages()->where('status', EhImportPage::STATUS_DONE)->sum('file_size');
            $this->logEvent($import, "Concluído: {$comic->pages()->count()} de {$import->total_pages} páginas, {$failed} falhas, ".$this->formatBytes($size).'.');
        }

        return $this->progressPayload($comic, $import?->fresh(), $complete);
    }

    /**
     * Append a timestamped line to the import log (last 200 kept).
     */
    protected function logEvent(EhImport $import, string $message): void
    {
        $lines = array_values(array_filter(explode("\n", (string) $import->log)));
        $lines[] = '['.now()->format('d/m H:i:s').'] '.$message;
        $import->update(['log' => implode("\n", array_slice($lines, -200))]);
    }

    /**
     * @return array<int, string> oldest-first log lines
     */
    protected function logLines(?EhImport $import): array
    {
        if ($import === null || $import->log === null || trim($import->log) === '') {
            return [];
        }

        return array_values(array_filter(explode("\n", $import->log)));
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }

    /**
     * @return array<int, int> failed page numbers, ascending
     */
    protected function failedNumbers(EhImport $import): array
    {
        return $import->pages()->where('status', EhImportPage::STATUS_FAILED)
            ->orderBy('page_number')->pluck('page_number')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * @return array<int, string> failed page number → last error message
     */
    protected function failedErrors(EhImport $import): array
    {
        return $import->pages()->where('status', EhImportPage::STATUS_FAILED)
            ->orderBy('page_number')
            ->get(['page_number', 'error'])
            ->mapWithKeys(fn (EhImportPage $row) => [(int) $row->page_number => (string) ($row->error ?? 'erro desconhecido')])
            ->all();
    }

    /**
     * @return array{done: int, total: int, failed: array<int, int>, errors: array<int, string>, pending: array<int, int>, pages: array<int, string>, details: array<int, array{status: string, attempts: int, error: string|null, size: int|null, image_url: string|null, page_url: string, updated: string|null}>, total_size: int, log: array<int, string>, complete: bool}
     */
    protected function progressPayload(Comic $comic, ?EhImport $import, bool $complete): array
    {
        $total = $import?->total_pages ?? $comic->pages()->count();

        return [
            'done' => $comic->pages()->count(),
            'total' => $total,
            'failed' => $import ? $this->failedNumbers($import) : [],
            'errors' => $import ? $this->failedErrors($import) : [],
            'pending' => $import ? $this->pendingNumbers($import) : [],
            'pages' => $this->pageMap($comic),
            'details' => $import ? $this->pageDetails($import) : [],
            'total_size' => $import ? (int) $import->pages()->where('status', EhImportPage::STATUS_DONE)->sum('file_size') : 0,
            'log' => $this->logLines($import),
            'complete' => $complete,
        ];
    }

    /**
     * Full per-page inspection data for the progress view modal.
     *
     * @return array<int, array{status: string, attempts: int, error: string|null, size: int|null, image_url: string|null, page_url: string, updated: string|null}>
     */
    protected function pageDetails(EhImport $import): array
    {
        return $import->pages()->orderBy('page_number')
            ->get(['page_number', 'status', 'attempts', 'error', 'file_size', 'image_url', 'page_url', 'updated_at'])
            ->mapWithKeys(fn (EhImportPage $row) => [(int) $row->page_number => [
                'status' => $row->status,
                'attempts' => (int) $row->attempts,
                'error' => $row->error,
                'size' => $row->file_size !== null ? (int) $row->file_size : null,
                'image_url' => $row->image_url,
                'page_url' => $row->page_url,
                'updated' => $row->updated_at?->format('d/m/Y H:i:s'),
            ]])
            ->all();
    }

    /**
     * @return array<int, string> page number → public thumbnail URL
     */
    protected function pageMap(Comic $comic): array
    {
        return $comic->pages()->orderBy('page_number')
            ->get(['page_number', 'image_path'])
            ->mapWithKeys(fn (Page $page) => [(int) $page->page_number => asset('storage/'.$page->image_path)])
            ->all();
    }

    /**
     * @return array<int, int> open page numbers, ascending
     */
    protected function pendingNumbers(EhImport $import): array
    {
        return $import->pages()
            ->whereIn('status', [EhImportPage::STATUS_PENDING, EhImportPage::STATUS_DOWNLOADING])
            ->orderBy('page_number')->pluck('page_number')->map(fn ($n) => (int) $n)->all();
    }

    protected function storePage(Comic $comic, string $bytes, string $extension, int $number): void
    {
        $dir = public_path("storage/comics/{$comic->id}/pages");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Drop stale files for this number (e.g. redownload with
        // another extension) so no orphans linger.
        foreach (glob($dir.'/page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.*') ?: [] as $stale) {
            @unlink($stale);
        }

        $filename = 'page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.'.$extension;
        file_put_contents($dir.'/'.$filename, $bytes);

        Page::updateOrCreate(
            ['comic_id' => $comic->id, 'page_number' => $number],
            ['image_path' => "comics/{$comic->id}/pages/{$filename}"]
        );
    }

    protected function ensureCover(Comic $comic): void
    {
        if ($comic->image_path !== null && $comic->image_path !== '') {
            return;
        }
        $first = $comic->pages()->orderBy('page_number')->first();
        if ($first !== null) {
            $comic->update(['image_path' => $first->image_path]);
        }
    }

    /**
     * Same short-name resolution as the tag editor: "male:dog boy"
     * stores "dog boy" in the namespace's category.
     */
    protected function syncTagsFromFullNames(Comic $comic, ?string $raw): void
    {
        $tagIds = [];
        foreach (explode(',', (string) $raw) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $tagIds[] = Tag::findOrCreateResolved($name)->id;
        }

        $comic->tags()->sync($tagIds);
    }

    /**
     * Suggested description: a link to the gallery labelled with the
     * uploader name (DescriptionText renders it as Markdown).
     *
     * @param  array{category?: string|null, uploader?: string|null, source_url: string, filecount: int}  $preview
     */
    protected function suggestedDescription(array $preview): string
    {
        $uploader = trim((string) ($preview['uploader'] ?? ''));

        if ($uploader === '') {
            return $preview['source_url'];
        }

        // Brackets in the name would break the [label](url) link.
        $label = trim(str_replace(['[', ']'], ['(', ')'], $uploader));

        return '['.$label.']('.$preview['source_url'].')';
    }
}
