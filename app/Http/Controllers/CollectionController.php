<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Comic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CollectionController extends Controller
{
    /**
     * Ownership columns exist only after the add_owner migration runs.
     * Hosts without console access may lag behind, so every ownership
     * feature degrades to the legacy global behavior instead of fataling.
     */
    protected function ownsColumns(): bool
    {
        return Schema::hasColumn('collections', 'user_id')
            && Schema::hasColumn('collections', 'is_public');
    }

    /**
     * The viewer's own collections (for the reader quick-add dropdown).
     */
    public function mine(Request $request)
    {
        $collections = $this->ownsColumns()
            ? $request->user()->collections()->with('comics:id')->orderBy('name')->get(['id', 'name', 'is_favorites'])
            : collect();

        if ($request->wantsJson()) {
            return response()->json(['data' => $collections]);
        }

        return redirect()->route('collections.index');
    }

    public function index()
    {
        $query = Collection::with(['comics' => fn ($query) => $query->orderBy('collection_comic.order')]);
        if ($this->ownsColumns()) {
            // Public collections plus the viewer's own (private included).
            $query->where(function ($query) {
                $query->where('is_public', true);
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            });
        }

        return view('collections.index', ['collections' => $query->latest()->get()]);
    }

    public function show(Request $request, Collection $collection)
    {
        $this->authorizeCollection($collection);

        $counts = [];
        if (Schema::hasTable('comments')) {
            $counts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $counts[] = 'likedByUsers';
        }

        $query = $collection->orderedComics()->withCount($counts);
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }
        $comics = $query->paginate(12)->withQueryString();

        $likedComicIds = [];
        if (auth()->check() && Schema::hasTable('comic_user_likes')) {
            $likedComicIds = DB::table('comic_user_likes')
                ->where('user_id', auth()->id())
                ->pluck('comic_id')
                ->all();
        }

        $canEdit = $this->canWrite($collection);
        $searchTerm = $request->input('q', '');

        return view('collections.show', compact('collection', 'comics', 'likedComicIds', 'canEdit', 'searchTerm'));
    }

    /**
     * Whether the viewer may see edit/delete controls (no abort).
     */
    protected function canWrite(Collection $collection): bool
    {
        if (! $this->ownsColumns() || $collection->user_id === null) {
            return auth()->check();
        }
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $collection->isOwnedBy($user) || (int) $user->admin_level >= 1;
    }

    /**
     * Private collections are visible to the owner (and admins) only.
     */
    protected function authorizeCollection(Collection $collection): void
    {
        if (! $this->ownsColumns()) {
            return; // Legacy global collections predate ownership.
        }
        if ($collection->is_public) {
            return;
        }
        $user = auth()->user();
        if ($user === null || (! $collection->isOwnedBy($user) && (int) $user->admin_level < 1)) {
            abort(403, 'This collection is private.');
        }
    }

    /**
     * Write access: owner or admin (legacy ownerless rows stay editable
     * so old hosts keep working until they migrate).
     */
    protected function authorizeCollectionWrite(Collection $collection): void
    {
        if (! $this->ownsColumns() || $collection->user_id === null) {
            return;
        }
        $user = auth()->user();
        if ($user === null || (! $collection->isOwnedBy($user) && (int) $user->admin_level < 1)) {
            abort(403, 'Only the collection owner can change it.');
        }
    }

    public function showById($id)
    {
        // Retrieve the collection along with its comics
        $collection = Collection::with('comics')->findOrFail($id);

        // Return the collection as JSON
        return response()->json($collection);
    }

    public function getAllCollections(Request $request)
    {
        $query = Collection::query();

        // Check if there is a search query
        if ($request->filled('search')) {
            $search = $request->input('search');

            // Filter by name or description
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
        }

        // Get the results
        $collections = $query->with(['comics' => fn ($query) => $query->orderBy('collection_comic.order')])->get(); // Include comics if needed

        // Return the collections as a JSON response
        return response()->json($collections);
    }

    public function create()
    {
        $comics = Comic::all(); // Fetch all comics for selection

        return view('collections.create', compact('comics'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
        ]);

        $attributes = $request->only('name', 'description');
        if ($this->ownsColumns()) {
            $attributes['user_id'] = $request->user()->id;
            $attributes['is_public'] = $request->boolean('is_public', true);
        }
        $collection = Collection::create($attributes);

        // Attach comics if any are selected
        if ($request->filled('comics')) {
            $collection->comics()->attach($request->comics);
        }

        if ($request->wantsJson()) {
            return response()->json($collection->fresh(), 201);
        }

        return redirect()->route('collections.create')->with('success', 'Collection created successfully.');
    }

    public function edit(Collection $collection)
    {
        $this->authorizeCollectionWrite($collection);
        $comics = Comic::orderBy('title')->get(['id', 'title', 'image_path']); // Fetch all comics for selection
        $selectedComics = $collection->orderedComics()->get(); // Comics already in the collection
        $supportsOwnership = $this->ownsColumns();

        return view('collections.edit', compact('collection', 'comics', 'selectedComics', 'supportsOwnership'));
    }

    public function update(Request $request, Collection $collection)
    {
        $this->authorizeCollectionWrite($collection);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
        ]);

        $attributes = $request->only('name', 'description');
        if ($this->ownsColumns()) {
            $attributes['is_public'] = $request->boolean('is_public', true);
        }
        $collection->update($attributes);

        // Sync comics only when the picker submitted (an absent key means
        // "untouched" — syncing null would wipe the whole collection).
        if ($request->has('comics')) {
            $collection->comics()->sync($request->input('comics', []));
        }

        return redirect()->route('collections.edit', $collection)->with('success', 'Collection updated successfully.');
    }

    public function destroy(Collection $collection)
    {
        $this->authorizeCollectionWrite($collection);
        $collection->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('collections.index')->with('success', 'Collection deleted.');
    }

    /**
     * Add one comic (quick-add dropdown / favorite shortcut).
     */
    public function addComic(Request $request, Collection $collection, Comic $comic)
    {
        $this->authorizeCollectionWrite($collection);

        if (! $collection->comics()->where('comics.id', $comic->id)->exists()) {
            $maxOrder = (int) $collection->comics()->max('collection_comic.order');
            $collection->comics()->attach($comic->id, ['order' => $maxOrder + 1]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'added' => true,
                'count' => $collection->comics()->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Added to collection.');
    }

    /**
     * Remove one comic from a collection.
     */
    public function removeComic(Request $request, Collection $collection, Comic $comic)
    {
        $this->authorizeCollectionWrite($collection);
        $collection->comics()->detach($comic->id);

        if ($request->wantsJson()) {
            return response()->json([
                'removed' => true,
                'count' => $collection->comics()->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Removed from collection.');
    }

    /**
     * Toggle a comic in the viewer's built-in Favorites collection.
     */
    public function toggleFavorite(Request $request, Comic $comic)
    {
        $favorites = Collection::favoritesFor($request->user());

        if ($favorites->comics()->where('comics.id', $comic->id)->exists()) {
            $favorites->comics()->detach($comic->id);
            $favorited = false;
        } else {
            $maxOrder = (int) $favorites->comics()->max('collection_comic.order');
            $favorites->comics()->attach($comic->id, ['order' => $maxOrder + 1]);
            $favorited = true;
        }

        return response()->json([
            'favorited' => $favorited,
            'count' => $favorites->comics()->count(),
        ]);
    }

    public function updateSortOrder(Request $request, Collection $collection)
    {
        $order = $request->input('order'); // Get the new order from the request

        // Loop through the order and update each comic's position
        foreach ($order as $index => $comicId) {
            // Assuming you have a many-to-many relationship defined in the Collection model
            $collection->comics()->updateExistingPivot($comicId, ['order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
