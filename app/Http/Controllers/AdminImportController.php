<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\EhImport;
use App\Models\EhImportPage;
use App\Models\EhImportSetting;
use App\Models\Page;
use App\Models\Tag;
use App\Services\ComicDuplicateFinder;
use App\Services\EhComicImporter;
use App\Services\EhImportException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
    ) {}

    /**
     * Step 1: URL form + upcoming schedule queue + tuning.
     */
    public function show(): View
    {
        return view('admin.import', [
            'stage' => 'form',
            'defaultDate' => Comic::defaultScheduledDate()->format('Y-m-d\\TH:i'),
            'queue' => Comic::scheduled()->with('ehImport:id,comic_id,total_pages')->withCount('pages')
                ->orderBy('published_at')->orderBy('id')->limit(8)->get(['id', 'title', 'published_at']),
            'settingDefs' => EhImportSetting::definitions(),
        ]);
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

        $lock = Cache::lock("ehimport:import:{$import->id}:lock", 120);
        if (! $lock->get()) {
            return response()->json(['message' => 'Outra requisição está baixando. Aguarde.'], 409);
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
                return response()->json($this->finishImport($comic->fresh()));
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

            if ($limited && $processed === 0) {
                $retryAfter = max(1, RateLimiter::availableIn('ehimport:images'));

                return response()->json(
                    ['message' => 'Limite de downloads por minuto atingido — aguardando.', 'retry_after' => $retryAfter],
                    429,
                    ['Retry-After' => (string) $retryAfter]
                );
            }

            return response()->json($this->finishImport($comic->fresh()));
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
            $preview = $this->importer->previewFromUrl($request->input('url'));
        } catch (EhImportException $e) {
            return redirect()->route('admin.import.progress', $comic)->with('error', $e->getMessage());
        }

        $comic->pages()->delete();
        File::deleteDirectory(public_path("storage/comics/{$comic->id}/pages"));
        $comic->update(['image_path' => null]);
        $comic->ehImport?->delete();

        $import = $this->seedImport($comic, $preview);
        $this->logEvent($import, 'Baixar tudo de novo: páginas antigas apagadas, fila zerada.');

        return redirect()->route('admin.import.progress', $comic)
            ->with('success', count($preview['page_urls']).' página(s) na fila — baixando de novo em ordem.');
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
