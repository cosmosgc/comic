<?php

namespace App\Http\Controllers;

use App\Models\Comic;
use App\Models\Page;
use App\Models\Tag;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        $query = Comic::withCount($counts);
        if ($sort === 'liked') {
            $query->orderByDesc('liked_by_users_count')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }
        $comics = $query->paginate(10)->withQueryString();
        $likedComicIds = Auth::check() && Schema::hasTable('comic_user_likes')
            ? DB::table('comic_user_likes')->where('user_id', Auth::id())->pluck('comic_id')->all()
            : [];
        $widgets = Widget::all();
        $showPanels = true;
        // Get IDs of the last 20 comics posted
        $lastTwentyIds = Comic::orderBy('created_at', 'desc')
            ->take(20)
            ->pluck('id');

        // Now get top 5 from those 20 by view_count
        $topComics = Comic::withCount($counts)
            ->whereIn('id', $lastTwentyIds)
            ->orderBy('view_count', 'desc')
            ->take(5)
            ->get();
        $tags = Tag::all();

        return view('comics.index', compact('comics', 'topComics', 'tags', 'showPanels', 'widgets', 'likedComicIds', 'sort'));
    }

    public function create()
    {
        return view('comics.upload');
    }

    public function show($id)
    {
        $comic = Comic::findOrFail($id);

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
        $comic->increment('view_count');

        return view('comics.show', array_merge(compact('comic'), $this->readerEngagement($comic)));
    }

    // Method to show a comic by its slug
    public function showBySlug($slug)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->where('slug', $slug)->firstOrFail();
        $comic->increment('view_count');

        return view('comics.show', array_merge(compact('comic'), $this->readerEngagement($comic)));
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
        $query = Comic::query();

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
        $comic->increment('view_count');

        // Return the comic and its pages as a single JSON response
        return response()->json($comic);
    }

    public function getAllComics(Request $request)
    {
        $query = Comic::query();

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
        ]);

        // Generate slug if not provided
        $slug = $request->input('slug') ?: Comic::generateUniqueSlug($request->input('title'));

        // Create the comic entry
        $comic = Comic::create([
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'description' => $request->input('desc'),
            'slug' => $slug,
            'user_id' => Auth::id(),
        ]);

        // Handle tags
        if ($request->filled('tags')) {
            $tagNames = explode(',', $request->input('tags'));
            $tagIds = [];

            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }

            $comic->tags()->sync($tagIds);
        }

        // Define the public folder path
        $comicFolderPath = public_path("storage/comics/{$comic->id}");

        // Check if the folder exists, if not, create it
        if (! file_exists($comicFolderPath)) {
            mkdir($comicFolderPath, 0777, true);
        }

        $firstImagePath = null;

        // Handle image uploads
        if ($request->hasFile('folder')) {
            $pageNumber = 1;

            foreach ($request->file('folder') as $file) {
                if ($file->isValid()) {
                    $originalFileName = $file->getClientOriginalName();
                    $filePath = "{$comicFolderPath}/{$originalFileName}";

                    // Move file to the public directory
                    $file->move($comicFolderPath, $originalFileName);

                    if ($pageNumber === 1) {
                        $firstImagePath = "comics/{$comic->id}/{$originalFileName}"; // Relative path
                    }

                    // Create a new Page entry for each image
                    Page::create([
                        'comic_id' => $comic->id,
                        'image_path' => "comics/{$comic->id}/{$originalFileName}", // Store relative path
                        'page_number' => $pageNumber,
                    ]);

                    $pageNumber++;
                }
            }
        } elseif ($request->hasFile('images')) {
            $pageNumber = 1;

            foreach ($request->file('images') as $file) {
                if ($file->isValid()) {
                    $originalFileName = $file->getClientOriginalName();
                    $filePath = "{$comicFolderPath}/{$originalFileName}";

                    // Move file to the public directory
                    $file->move($comicFolderPath, $originalFileName);

                    if ($pageNumber === 1) {
                        $firstImagePath = "comics/{$comic->id}/{$originalFileName}"; // Relative path
                    }

                    // Create a new Page entry for each image
                    Page::create([
                        'comic_id' => $comic->id,
                        'image_path' => "comics/{$comic->id}/{$originalFileName}", // Store relative path
                        'page_number' => $pageNumber,
                    ]);

                    $pageNumber++;
                }
            }
        }

        // Store the first image as the comic cover
        if ($firstImagePath) {
            $comic->update(['image_path' => $firstImagePath]);
        }

        return response()->json([
            'message' => 'Comic uploaded successfully.',
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

        return response()->json(['message' => 'Slugs updated for all comics without a slug.']);
    }

    public function edit($id)
    {
        $comic = Comic::with(['pages' => function ($query) {
            $query->orderBy('page_number');
        }])->findOrFail($id);

        return view('comics.edit', compact('comic'));
    }

    public function update(Request $request, Comic $comic)
    {

        $request->validate([
            'title' => 'required|string|max:255',
            // 'description' => 'required|string',
        ]);
        // Update comic details
        $comic->update([
            'title' => $request->input('title'),
            // 'description' => $request->input('description'),
        ]);

        return redirect()->route('comics.edit', $comic->id)->with('success', 'Comic updated successfully.');
    }

    public function reorderPages(Request $request, Comic $comic)
    {
        // The incoming request will be a JSON array of page data
        $orderedPages = $request->input();

        foreach ($orderedPages as $pageData) {
            $page = Page::findOrFail($pageData['id']);
            $page->update(['page_number' => $pageData['page_number']]);
        }

        return response()->json(['success' => true]);
    }

    public function deletePage($id)
    {
        $page = Page::findOrFail($id);

        $filePath = public_path('storage/'.$page->image_path);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $page->delete();

        return response()->json(['success' => true]);
    }

    public function setCover(Comic $comic, Request $request)
    {
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
