<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComicRequest;
use App\Http\Requests\UpdateComicRequest;
use App\Http\Resources\ComicResource;
use App\Models\Comic;
use App\Models\Page;
use App\Models\Tag;
use App\Models\Widget;
use App\Support\ActivityLogger;
use App\Support\ImageStore;
use App\View\Composers\SidebarComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ComicController extends Controller
{
    public function index(Request $request)
    {
        // Counts degrade gracefully on hosts missing the new tables.
        // The collections pivot predates all migrations, always countable.
        $counts = ['collections'];
        if (Schema::hasTable('comments')) {
            $counts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $counts[] = 'likedByUsers';
        }

        // ?sort=liked ranks by likes (needs the likes table, else latest).
        $sort = $request->input('sort') === 'liked' && in_array('likedByUsers', $counts, true)
            ? 'liked'
            : 'latest';
        // ?filter=exclusivo narrows the list to members-only comics. Guests
        // reach the same page and get locked teaser cards, so the filter
        // doubles as the pitch to sign up.
        $filter = $request->input('filter') === 'exclusivo' ? 'exclusivo' : null;

        // visibleTo() is the whole visibility matrix: released comics for
        // everyone, the last week before release plus exclusives for
        // members, and every queue for owners/admins.
        $query = Comic::withCount($counts)->visibleTo(Auth::user());
        if ($filter === 'exclusivo' && Schema::hasColumn('comics', 'is_exclusive')) {
            $query->where('is_exclusive', true);
        }
        if ($sort === 'liked') {
            // Most liked first, but the tie (and the plain "recentes" tab)
            // follows the release date, not the upload date.
            $query->orderByDesc('liked_by_users_count')->orderByReleaseDate();
        } else {
            $query->orderByReleaseDate();
        }
        $comics = $query->paginate(10)->withQueryString();
        $likedComicIds = Auth::check() && Schema::hasTable('comic_user_likes')
            ? DB::table('comic_user_likes')->where('user_id', Auth::id())->pluck('comic_id')->all()
            : [];
        // Card layout preference (cookie set by the grid/list toggle).
        $layout = $request->cookie('comics_layout') === 'list' ? 'list' : 'grid';
        $widgets = Widget::all();
        $showPanels = true;
        // Get IDs of the last 20 comics by release date (the pool the
        // "most viewed" panel ranks).
        $lastTwentyIds = Comic::published()->orderByReleaseDate()
            ->take(20)
            ->pluck('id');

        // Now get top 5 from those 20 by view_count.
        $topComics = Comic::published()->withCount($counts)
            ->whereIn('id', $lastTwentyIds)
            ->orderBy('view_count', 'desc')
            ->take(5)
            ->get();
        // Sidebar: popular tags are like-driven, limited, with a
        // randomized threshold (see SidebarComposer).
        $tags = SidebarComposer::popularTags();
        $latestComics = SidebarComposer::latestUploads();
        $recommendedComics = SidebarComposer::recommended();

        return view('comics.index', compact('comics', 'topComics', 'tags', 'showPanels', 'widgets', 'likedComicIds', 'sort', 'filter', 'latestComics', 'recommendedComics', 'layout'));
    }

    public function create()
    {
        return view('comics.upload');
    }

    public function show($id)
    {
        // Legacy entry point kept for old links: the reader is
        // showById()/showBySlug(), which also attach the engagement data
        // (so the view never falls back to unfiltered collections).
        return $this->showById($id);
    }

    /**
     * Engagement data for the reader (counts, viewer state, collections).
     * Degrades gracefully on hosts missing the new tables.
     */
    protected function readerEngagement(Comic $comic): array
    {
        $comic->loadCount(array_filter([
            Schema::hasTable('comments') ? 'comments' : null,
            Schema::hasTable('comic_user_likes') ? 'likedByUsers' : null,
        ]));

        $liked = Auth::check()
            && Schema::hasTable('comic_user_likes')
            && $comic->likedByUsers()->where('user_id', Auth::id())->exists();

        if (Schema::hasColumn('collections', 'is_public')) {
            // Private collections stay invisible unless owned by the
            // viewer. Filtered in SQL (not in PHP) so the reader does not
            // hydrate every collection that references the comic.
            $viewer = Auth::user();
            $collections = $comic->collections()
                ->where(function ($inner) use ($viewer) {
                    $inner->where('collections.is_public', true);
                    if ($viewer !== null) {
                        $inner->orWhere('collections.user_id', $viewer->id);
                    }
                })
                ->get();
        } else {
            $collections = $comic->collections;
        }

        $myCollections = Auth::check() && Schema::hasColumn('collections', 'user_id')
            ? Auth::user()->collections()->orderBy('name')->get(['id', 'name', 'is_favorites'])
            : collect();

        return [
            'liked' => $liked,
            'visibleCollections' => $collections,
            'myCollections' => $myCollections,
        ];
    }

    // In your ComicController or equivalent
    public function showById($id)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->findOrFail($id);
        abort_unless($comic->isVisibleTo(Auth::user()), 404);
        $comic->increment('view_count');
        $this->logReading($comic);

        return view('comics.show', array_merge(compact('comic'), $this->readerEngagement($comic)));
    }

    // Method to show a comic by its slug
    public function showBySlug($slug)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->where('slug', $slug)->firstOrFail();
        abort_unless($comic->isVisibleTo(Auth::user()), 404);
        $comic->increment('view_count');
        $this->logReading($comic);

        return view('comics.show', array_merge(compact('comic'), $this->readerEngagement($comic)));
    }

    /**
     * Feed the admin "Atividades recentes" panel with a "started
     * reading" entry the first time a signed-in user opens a comic.
     * Guests are skipped (nothing to attribute, and anonymous traffic
     * would flood the feed) and scheduled comics are ignored until
     * they are actually released.
     */
    protected function logReading(Comic $comic): void
    {
        if (! $comic->isPublished()) {
            return;
        }

        if (Auth::user() === null || ActivityLogger::alreadyLogged('comic.reading', $comic)) {
            return;
        }

        ActivityLogger::log('comic.reading', [
            'subject' => $comic,
            'description' => 'Começou a ler "'.$comic->title.'"',
        ]);
    }

    /**
     * Handle search and filtering of comics by text or tag.
     *
     * This method retrieves comics from the database based on optional query parameters:
     * - `search`: Filters comics whose title or author contains the given search term.
     * - `tag`: Filters comics associated with a specific tag name.
     * Both filters can be combined — if both are provided, only comics matching both
     * conditions will be returned.
     *
     * Usage examples:
     * - /search?search=Batman          → Finds comics with "Batman" in title or author.
     * - /search?tag=Action            → Finds comics with the "Action" tag.
     * - /search?search=Batman&tag=DC  → Finds comics with "Batman" in title/author and tagged "DC".
     *
     * @return View
     */
    public function search(Request $request)
    {
        // Members see the same catalogue as the home list (early access +
        // exclusives); guests only see released comics.
        $query = Comic::query()->visibleTo($request->user());

        // 🔎 Text search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        // 🏷️ Tag filter
        if ($request->filled('tag')) {
            $tagName = $request->input('tag');
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('name', $tagName);
            });
        }

        $searchCounts = [];
        if (Schema::hasTable('comments')) {
            $searchCounts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $searchCounts[] = 'likedByUsers';
        }
        // Same timeline order as the home list, so results do not come
        // back in raw insertion order.
        $comics = $query->orderByReleaseDate()->withCount($searchCounts)->paginate(10);
        $likedComicIds = Auth::check() && Schema::hasTable('comic_user_likes')
            ? DB::table('comic_user_likes')->where('user_id', Auth::id())->pluck('comic_id')->all()
            : [];

        if ($request->filled('search') || $request->filled('tag')) {
            $parts = [];
            if ($request->filled('search')) {
                $parts[] = '"'.$request->input('search').'"';
            }
            if ($request->filled('tag')) {
                $parts[] = 'tag '.$request->input('tag');
            }
            ActivityLogger::log('search.comics', [
                'user' => $request->user(),
                'description' => 'Buscou comics por '.implode(' + ', $parts),
                'url' => $request->fullUrl(),
                'metadata' => [
                    'search' => $request->input('search'),
                    'tag' => $request->input('tag'),
                    'results' => $comics->total(),
                ],
                'ip' => $request->ip(),
            ]);
        }

        return view('comics.search', [
            'comics' => $comics,
            'searchTerm' => $request->input('search'),
            'tagTerm' => $request->input('tag'),
            'likedComicIds' => $likedComicIds,
        ]);
    }

    public function getComic($id)
    {
        // Fetch the comic along with its related pages
        $comic = Comic::with('pages')->findOrFail($id);
        abort_unless($comic->isVisibleTo(Auth::user()), 404);
        $comic->increment('view_count');

        // Return the comic and its pages as a single JSON response
        return response()->json(new ComicResource($comic));
    }

    public function getAllComics(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'tag' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Comic::query()->published();

        // Check if there is a search query
        if (! empty($validated['search'])) {
            $search = $validated['search'];

            // Filter by title or author. Grouped so the OR can never
            // escape `published()` and expose a scheduled comic.
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('author', 'like', '%'.$search.'%');
            });
        }
        if (! empty($validated['tag'])) {
            $tagName = $validated['tag'];
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('name', $tagName);
            });
        }

        // A browse call (no search term) is capped so the payload stays
        // bounded. Searches are already narrowed by the LIKE filter, and
        // the upload form's duplicate check needs every title match.
        if (! empty($validated['limit'])) {
            $query->limit((int) $validated['limit']);
        } elseif (empty($validated['search'])) {
            $query->limit(100);
        }

        // Get the results
        $comics = $query->get();

        // Return the comics as a JSON response
        return response()->json(ComicResource::collection($comics));
    }

    public function store(StoreComicRequest $request)
    {
        // A client-supplied slug still goes through Str::slug() and the
        // uniqueness loop — and falls back to the title when it sanitizes
        // to nothing — so it can never collide (unique index → 500) or
        // carry invalid characters.
        $requestedSlug = trim((string) $request->input('slug'));
        $slug = Comic::generateUniqueSlug($requestedSlug !== '' ? $requestedSlug : (string) $request->input('title'));

        // Create the comic entry
        $attributes = [
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'description' => $request->input('desc'),
            'slug' => $slug,
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
            'user_id' => Auth::id(),
        ];

        // Members-only upload: skipped on hosts whose comics table predates
        // the column, so an optional perk can never break the upload.
        if (Schema::hasColumn('comics', 'is_exclusive')) {
            $attributes['is_exclusive'] = $request->boolean('is_exclusive');
        }

        $comic = Comic::create($attributes);

        // Handle tags
        if ($request->filled('tags')) {
            $this->syncTagsFromString($comic, $request->input('tags'));
        }

        // Pages may arrive as `folder[]` (directory picker) or `images[]`
        // (file picker). Names are generated server-side, so both branches
        // store exactly the same way (putInStorage creates the folder).
        $firstImagePath = null;
        $pageNumber = 1;

        foreach ($request->file('folder') ?? $request->file('images') ?? [] as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $relativePath = ImageStore::putInStorage($file, "comics/{$comic->id}");

            if ($pageNumber === 1) {
                $firstImagePath = $relativePath;
            }

            Page::create([
                'comic_id' => $comic->id,
                'image_path' => $relativePath,
                'page_number' => $pageNumber,
            ]);

            $pageNumber++;
        }

        // Store the first image as the comic cover
        if ($firstImagePath) {
            $comic->update(['image_path' => $firstImagePath]);
        }

        ActivityLogger::log('comic.uploaded', [
            'user' => $request->user(),
            'subject' => $comic->fresh(),
            'description' => 'Enviou o comic "'.$comic->title.'"',
            'metadata' => ['comic_id' => $comic->id, 'pages' => $comic->pages()->count()],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Comic publicado com sucesso.',
            'redirect' => route('comics.showBySlug', $comic->slug),
            // Lets the upload page append remaining pages one small
            // request at a time (avoids nginx 413 on huge single POSTs).
            'comic_id' => $comic->id,
        ]);

    }

    public function edit($id)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->findOrFail($id);
        $this->authorize('update', $comic);

        return view('comics.edit', compact('comic'));
    }

    /**
     * Parse a comma-separated tag string, create missing tags (with
     * auto-detected categories), and sync.
     * Empty fragments are skipped; matching is exact (case preserved).
     */
    protected function syncTagsFromString(Comic $comic, ?string $raw): void
    {
        $tagIds = [];
        foreach (explode(',', (string) $raw) as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }
            $tagIds[] = Tag::findOrCreateCategorized($tagName)->id;
        }

        $comic->tags()->sync($tagIds);
    }

    public function update(UpdateComicRequest $request, Comic $comic)
    {
        $validated = $request->validated();

        $this->authorize('update', $comic);
        // Update comic details
        $attributes = [
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
        ];

        // Owners (and admins) may flag their own comic as members-only. Only
        // touched when the form sent the field, so callers that omit it
        // (tests, API-ish update calls) never flip the flag by accident.
        if ($request->has('is_exclusive') && Schema::hasColumn('comics', 'is_exclusive')) {
            $attributes['is_exclusive'] = $request->boolean('is_exclusive');
        }

        $comic->update($attributes);

        // Sync tags only when the editor submitted (an absent key means
        // "untouched" — syncing null would wipe all tags).
        if ($request->has('tags')) {
            $this->syncTagsFromString($comic, $validated['tags'] ?? null);
        }

        return redirect()->route('comics.edit', $comic->id)->with('success', 'Comic atualizado com sucesso.');
    }

    public function reorderPages(Request $request, Comic $comic)
    {
        $this->authorize('update', $comic);

        // Validate shape first: unknown ids or non-numeric positions 422.
        // Scoping the update to $comic->pages() stops callers from
        // renumbering pages that belong to other comics.
        $orderedPages = $request->validate([
            '*.id' => 'required|integer|exists:pages,id',
            '*.page_number' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($comic, $orderedPages) {
            foreach ($orderedPages as $pageData) {
                $comic->pages()->where('id', $pageData['id'])->update(['page_number' => $pageData['page_number']]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function deletePage($id)
    {
        $page = Page::findOrFail($id);
        abort_unless($page->comic !== null, 404);
        $this->authorize('update', $page->comic);

        $filePath = public_path('storage/'.$page->image_path);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $page->delete();

        return response()->json(['success' => true]);
    }

    public function setCover(Comic $comic, Request $request)
    {
        $this->authorize('update', $comic);

        $request->validate([
            'page_id' => 'required|exists:pages,id',
        ]);

        $page = Page::where('id', $request->page_id)
            ->where('comic_id', $comic->id) // security check
            ->firstOrFail();

        $comic->image_path = $page->image_path;
        $comic->save();

        return response()->json([
            'success' => true,
            'image_path' => asset('storage/'.$comic->image_path),
        ]);
    }
}
