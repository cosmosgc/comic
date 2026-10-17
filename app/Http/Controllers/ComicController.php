<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Str;
use Illuminate\View\View;

class ComicController extends Controller
{
    /**
     * Owner-or-admin gate for comic mutation. Guests are blocked by the
     * `auth` route middleware; this stops non-owners from touching
     * other users' comics (admins bypass via admin_level >= 1).
     */
    protected function authorizeComicAccess(Comic $comic): void
    {
        $user = Auth::user();
        abort_unless(
            $user !== null && ((int) $comic->user_id === (int) $user->id || (int) $user->admin_level >= 1),
            403
        );
    }

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
        $query = Comic::withCount($counts)->published();
        if ($sort === 'liked') {
            $query->orderByDesc('liked_by_users_count')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }
        $comics = $query->paginate(10)->withQueryString();
        $likedComicIds = Auth::check() && Schema::hasTable('comic_user_likes')
            ? DB::table('comic_user_likes')->where('user_id', Auth::id())->pluck('comic_id')->all()
            : [];
        // Card layout preference (cookie set by the grid/list toggle).
        $layout = $request->cookie('comics_layout') === 'list' ? 'list' : 'grid';
        $widgets = Widget::all();
        $showPanels = true;
        // Get IDs of the last 20 published comics posted
        $lastTwentyIds = Comic::published()->orderBy('created_at', 'desc')
            ->take(20)
            ->pluck('id');

        // Now get top 5 from those 20 by view_count
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

        return view('comics.index', compact('comics', 'topComics', 'tags', 'showPanels', 'widgets', 'likedComicIds', 'sort', 'latestComics', 'recommendedComics', 'layout'));
    }

    public function create()
    {
        return view('comics.upload');
    }

    public function show($id)
    {
        $comic = Comic::findOrFail($id);
        abort_unless($comic->isVisibleTo(Auth::user()), 404);

        return view('comics.show', compact('comic'));
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

        $collections = $comic->collections;
        if (Schema::hasColumn('collections', 'is_public')) {
            // Private collections stay invisible unless owned by the viewer.
            $collections = $collections->filter(
                fn ($collection) => $collection->is_public || $collection->isOwnedBy(Auth::user())
            )->values();
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
        $query = Comic::query()->published();

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
        $comics = $query->withCount($searchCounts)->paginate(10);
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
        return response()->json($comic);
    }

    public function getAllComics(Request $request)
    {
        $query = Comic::query()->published();

        // Check if there is a search query
        if ($request->filled('search')) {
            $search = $request->input('search');

            // Filter by title or author
            $query->where('title', 'like', '%'.$search.'%')
                ->orWhere('author', 'like', '%'.$search.'%');
        }
        if ($request->filled('tag')) {
            $tagName = $request->input('tag');
            $query->whereHas('tags', function ($q) use ($tagName) {
                $q->where('name', $tagName);
            });
        }

        // ⏳ Optional limit
        if ($request->has('limit')) {
            $query->limit((int) $request->input('limit'));
        }

        // Get the results
        $comics = $query->get();

        // Return the comics as a JSON response
        return response()->json($comics);
    }

    public function store(Request $request)
    {
        // Per-file cap (KB). The total-request cap is checked in the browser
        // before sending; nginx rejects oversized bodies with 413 before
        // Laravel ever runs, so this is the second line of defense (422).
        $maxFileKb = config('upload.max_file_mb', 10) * 1024;

        $request->validate([
            'title' => 'required|string|max:255',
            'folder' => 'required_without:images|array',
            'folder.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:'.$maxFileKb,
            'images' => 'required_without:folder|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:'.$maxFileKb,
            'published_at' => 'nullable|date',
        ]);

        // Generate slug if not provided
        $slug = $request->input('slug') ?: Comic::generateUniqueSlug($request->input('title'));

        // Create the comic entry
        $comic = Comic::create([
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'description' => $request->input('desc'),
            'slug' => $slug,
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
            'user_id' => Auth::id(),
        ]);

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

    public function updateMissingSlugs()
    {
        // Fetch all comics without a slug
        $comicsWithoutSlugs = Comic::whereNull('slug')->orWhere('slug', '')->get();

        foreach ($comicsWithoutSlugs as $comic) {
            // Generate a slug based on the title
            $slug = Str::slug($comic->title);

            // Check if the slug already exists
            $originalSlug = $slug;
            $count = 1;

            while (Comic::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$count;
                $count++;
            }

            // Update the comic with the new slug
            $comic->slug = $slug;
            $comic->save();
        }

        return response()->json(['message' => 'Slugs atualizados para todos os comics sem slug.']);
    }

    public function edit($id)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->findOrFail($id);
        $this->authorizeComicAccess($comic);

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

    public function update(Request $request, Comic $comic)
    {

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'tags' => 'nullable|string|max:2000',
            'published_at' => 'nullable|date',
        ]);
        $this->authorizeComicAccess($comic);
        // Update comic details
        $comic->update([
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'description' => $validated['description'] ?? null,
            'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
        ]);

        // Sync tags only when the editor submitted (an absent key means
        // "untouched" — syncing null would wipe all tags).
        if ($request->has('tags')) {
            $this->syncTagsFromString($comic, $validated['tags'] ?? null);
        }

        return redirect()->route('comics.edit', $comic->id)->with('success', 'Comic atualizado com sucesso.');
    }

    public function reorderPages(Request $request, Comic $comic)
    {
        $this->authorizeComicAccess($comic);

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
        $this->authorizeComicAccess($page->comic);

        $filePath = public_path('storage/'.$page->image_path);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $page->delete();

        return response()->json(['success' => true]);
    }

    public function setCover(Comic $comic, Request $request)
    {
        $this->authorizeComicAccess($comic);

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
