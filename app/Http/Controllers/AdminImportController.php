<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Page;
use App\Models\Tag;
use App\Services\EhComicImporter;
use App\Services\EhImportException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Full-comic import from E-Hentai (admin only).
 *
 * Flow: paste a gallery URL → preview (editable title, artist, tags,
 * schedule) → create the scheduled comic → poll the chunk endpoint
 * until every page is downloaded. Each poll downloads a few images so
 * slow shared hosts never hit execution limits; chunks are idempotent
 * (page numbers continue from the DB max).
 */
class AdminImportController extends Controller
{
    public function __construct(protected EhComicImporter $importer) {}

    /**
     * Step 1: URL form + upcoming schedule queue.
     */
    public function show()
    {
        return view('admin.import', [
            'stage' => 'form',
            'defaultDate' => Comic::defaultScheduledDate()->format('Y-m-d\\TH:i'),
            'queue' => Comic::scheduled()->orderBy('published_at')->orderBy('id')->limit(8)->get(['id', 'title', 'published_at']),
        ]);
    }

    /**
     * Step 2: fetch gallery metadata + page list, stash in cache,
     * render the editable form.
     */
    public function preview(Request $request)
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

        return view('admin.import', [
            'stage' => 'edit',
            'preview' => $preview,
            'previewToken' => $token,
            'defaultDate' => Comic::defaultScheduledDate()->format('Y-m-d\\TH:i'),
            'suggestedDescription' => $this->suggestedDescription($preview),
        ]);
    }

    /**
     * Step 3: create the scheduled comic, resolve tags, seed the page
     * queue, hand off to the progress view.
     */
    public function store(Request $request)
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

        $comic = Comic::create([
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'slug' => Comic::generateUniqueSlug($validated['title']),
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
            'user_id' => Auth::id(),
        ]);

        $this->syncTagsFromFullNames($comic, $validated['tags'] ?? null);

        Cache::put(
            "ehimport:state:{$comic->id}",
            [
                'remaining' => array_values($cached['page_urls']),
                'total' => count($cached['page_urls']),
                'failed' => [],
                'attempts' => [],
            ],
            now()->addSeconds(max(3600, (int) config('ehimport.state_ttl', 21600)))
        );
        Cache::forget("ehimport:preview:{$validated['preview_token']}");

        return redirect()->route('admin.import.progress', $comic)
            ->with('success', 'Comic criado e agendado. Baixando as páginas…');
    }

    /**
     * Progress view: polls fetchPages until complete.
     */
    public function progress(Comic $comic)
    {
        $state = Cache::get("ehimport:state:{$comic->id}");

        return view('admin.import', [
            'stage' => 'progress',
            'comic' => $comic,
            'total' => $state['total'] ?? $comic->pages()->count(),
            'done' => $comic->pages()->count(),
        ]);
    }

    /**
     * Download the next chunk of pages (polled as JSON).
     */
    public function fetchPages(Comic $comic): JsonResponse
    {
        $key = "ehimport:state:{$comic->id}";
        $lock = Cache::lock("{$key}:lock", 120);
        if (! $lock->get()) {
            return response()->json(['message' => 'Outra requisição está baixando. Aguarde.'], 409);
        }

        try {
            $state = Cache::get($key);
            if (! is_array($state) || ($state['remaining'] ?? []) === []) {
                return response()->json($this->progressPayload($comic, $state, true));
            }

            $chunk = array_splice($state['remaining'], 0, max(1, (int) config('ehimport.chunk', 3)));
            foreach ($chunk as $pageUrl) {
                try {
                    $imageUrl = $this->importer->resolveImageUrl($pageUrl);
                    $file = $this->importer->downloadImage($imageUrl);
                    $this->storePage($comic, $file['bytes'], $file['extension']);
                    unset($state['attempts'][$pageUrl]);
                } catch (EhImportException) {
                    $attempts = ($state['attempts'][$pageUrl] ?? 0) + 1;
                    if ($attempts >= max(1, (int) config('ehimport.max_attempts', 3))) {
                        $state['failed'][] = $pageUrl;
                        unset($state['attempts'][$pageUrl]);
                    } else {
                        $state['attempts'][$pageUrl] = $attempts;
                        $state['remaining'][] = $pageUrl;
                    }
                }
            }

            if ($state['remaining'] === []) {
                $this->ensureCover($comic->fresh());
                Cache::forget($key);

                return response()->json($this->progressPayload($comic->fresh(), $state, true));
            }

            Cache::put($key, $state, now()->addSeconds(max(3600, (int) config('ehimport.state_ttl', 21600))));

            return response()->json($this->progressPayload($comic->fresh(), $state, false));
        } finally {
            $lock->release();
        }
    }

    /**
     * Cancel an import: removes the comic, its pages, files and queue.
     */
    public function destroy(Comic $comic)
    {
        Cache::forget("ehimport:state:{$comic->id}");
        $comic->pages()->delete();
        File::deleteDirectory(public_path("storage/comics/{$comic->id}"));
        $comic->delete();

        return redirect()->route('admin.import')->with('success', 'Importação cancelada e comic removido.');
    }

    /**
     * @param  array{total: int, remaining?: array, failed?: array}|null  $state
     * @return array{done: int, total: int, failed: array<int, string>, complete: bool}
     */
    protected function progressPayload(Comic $comic, ?array $state, bool $complete): array
    {
        $total = $state['total'] ?? $comic->pages()->count();
        $done = $complete ? $comic->pages()->count() : $total - count($state['remaining'] ?? []);

        return [
            'done' => $done,
            'total' => $total,
            'failed' => array_values($state['failed'] ?? []),
            'complete' => $complete,
        ];
    }

    protected function storePage(Comic $comic, string $bytes, string $extension): void
    {
        $number = (int) ($comic->pages()->max('page_number') ?? 0) + 1;
        $dir = public_path("storage/comics/{$comic->id}/pages");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.'.$extension;
        file_put_contents($dir.'/'.$filename, $bytes);

        Page::create([
            'comic_id' => $comic->id,
            'image_path' => "comics/{$comic->id}/pages/{$filename}",
            'page_number' => $number,
        ]);
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
     * @param  array{category?: string|null, source_url: string, filecount: int}  $preview
     */
    protected function suggestedDescription(array $preview): string
    {
        $parts = ['Importado do E-Hentai'];
        if (! empty($preview['category'])) {
            $parts[] = '('.$preview['category'].')';
        }

        return implode(' ', $parts).' — '.$preview['source_url'];
    }
}
