<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use App\Models\Comic;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

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
    public function mine(Request $request): JsonResponse|RedirectResponse
    {
        $collections = $this->ownsColumns()
            ? $request->user()->collections()->with('comics:id')->orderBy('name')->get(['id', 'name', 'is_favorites'])
            : collect();

        if ($request->wantsJson()) {
            return response()->json(['data' => $collections]);
        }

        return redirect()->route('collections.index');
    }

    public function index(): View
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

    public function show(Request $request, Collection $collection): View
    {
        $this->authorize('view', $collection);

        $counts = [];
        if (Schema::hasTable('comments')) {
            $counts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $counts[] = 'likedByUsers';
        }

        $query = $collection->orderedComics()->visibleTo($request->user())->withCount($counts);
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

        $canEdit = auth()->user()?->can('update', $collection) ?? false;
        $searchTerm = $request->input('q', '');

        return view('collections.show', compact('collection', 'comics', 'likedComicIds', 'canEdit', 'searchTerm'));
    }

    public function showById($id): JsonResponse
    {
        // Retrieve the collection along with its comics. Private
        // collections (e.g. a user's Favorites) are not exposed by the
        // public API: for guests this narrows to is_public = true.
        $query = Collection::with('comics');

        if ($this->ownsColumns()) {
            $query->where(function ($query) {
                $query->where('is_public', true);
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            });
        }

        // Return the collection as JSON
        return response()->json(new CollectionResource($query->findOrFail($id)));
    }

    public function getAllCollections(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Collection::query();

        if ($this->ownsColumns()) {
            // Public collections plus the viewer's own (private included).
            $query->where(function ($query) {
                $query->where('is_public', true);
                if (auth()->check()) {
                    $query->orWhere('user_id', auth()->id());
                }
            });
        }

        // Check if there is a search query
        if (! empty($validated['search'])) {
            $search = $validated['search'];

            // Filter by name or description (grouped OR, see getAllComics).
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        // Get the results — capped so an unfiltered call cannot dump the
        // whole table (with every comic of every collection) in one hit.
        $collections = $query
            ->with(['comics' => fn ($query) => $query->orderBy('collection_comic.order')])
            ->limit((int) ($validated['limit'] ?? 50))
            ->get();

        // Return the collections as a JSON response
        return response()->json(CollectionResource::collection($collections));
    }

    public function create(): View
    {
        $comics = Comic::all(); // Fetch all comics for selection

        return view('collections.create', compact('comics'));
    }

    public function store(CollectionRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
        if ($this->ownsColumns()) {
            $attributes['user_id'] = $request->user()->id;
            $attributes['is_public'] = $request->boolean('is_public', true);
        }
        $collection = Collection::create($attributes);

        // Attach comics if any are selected (ids validated above).
        if (! empty($validated['comics'])) {
            $collection->comics()->attach($validated['comics']);
        }

        ActivityLogger::log('collection.created', [
            'user' => $request->user(),
            'subject' => $collection,
            'description' => 'Criou a coleção "'.$collection->name.'"',
            'metadata' => ['collection_id' => $collection->id, 'comics' => $collection->comics()->count()],
            'ip' => $request->ip(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(new CollectionResource($collection->fresh()), 201);
        }

        return redirect()->route('collections.create')->with('success', 'Coleção criada com sucesso.');
    }

    public function edit(Collection $collection): View
    {
        $this->authorize('update', $collection);
        $comics = Comic::orderBy('title')->get(['id', 'title', 'image_path']); // Fetch all comics for selection
        $selectedComics = $collection->orderedComics()->get(); // Comics already in the collection
        $supportsOwnership = $this->ownsColumns();

        return view('collections.edit', compact('collection', 'comics', 'selectedComics', 'supportsOwnership'));
    }

    public function update(CollectionRequest $request, Collection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);

        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];
        if ($this->ownsColumns()) {
            // Unchecked checkbox submits nothing, which means private.
            // (Create defaults to public instead — see store().)
            $attributes['is_public'] = $request->boolean('is_public');
        }
        $collection->update($attributes);

        // Sync comics only when the picker submitted (an absent key means
        // "untouched" — syncing null would wipe the whole collection).
        if ($request->has('comics')) {
            $collection->comics()->sync($validated['comics'] ?? []);
        }

        return redirect()->route('collections.edit', $collection)->with('success', 'Coleção atualizada com sucesso.');
    }

    public function destroy(Collection $collection): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $collection);
        $collection->delete();

        if (request()->wantsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('collections.index')->with('success', 'Coleção excluída.');
    }

    /**
     * Add one comic (quick-add dropdown / favorite shortcut).
     */
    public function addComic(Request $request, Collection $collection, Comic $comic): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $collection);

        if (! $collection->comics()->where('comics.id', $comic->id)->exists()) {
            $maxOrder = (int) $collection->comics()->max('collection_comic.order');
            $collection->comics()->attach($comic->id, ['order' => $maxOrder + 1]);

            ActivityLogger::log('collection.added', [
                'user' => $request->user(),
                'subject' => $comic,
                'url' => ActivityLogger::urlFor($collection),
                'description' => 'Adicionou "'.$comic->title.'" à coleção "'.$collection->name.'"',
                'metadata' => ['collection_id' => $collection->id, 'comic_id' => $comic->id],
                'ip' => $request->ip(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'added' => true,
                'count' => $collection->comics()->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Adicionado à coleção.');
    }

    /**
     * Remove one comic from a collection.
     */
    public function removeComic(Request $request, Collection $collection, Comic $comic): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $collection);
        $collection->comics()->detach($comic->id);

        if ($request->wantsJson()) {
            return response()->json([
                'removed' => true,
                'count' => $collection->comics()->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Removido da coleção.');
    }

    /**
     * Toggle a comic in the viewer's built-in Favorites collection.
     */
    public function toggleFavorite(Request $request, Comic $comic): JsonResponse
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

        ActivityLogger::log($favorited ? 'collection.favorited' : 'collection.unfavorited', [
            'user' => $request->user(),
            'subject' => $comic,
            'description' => ($favorited ? 'Favoritou "' : 'Removeu dos favoritos "').$comic->title.'"',
            'metadata' => ['comic_id' => $comic->id, 'favorited' => $favorited],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'favorited' => $favorited,
            'count' => $favorites->comics()->count(),
        ]);
    }

    public function updateSortOrder(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('update', $collection);

        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:comics,id',
        ]);
        $order = $request->input('order'); // Get the new order from the request

        // Only reorder comics actually in this collection.
        $memberIds = $collection->comics()->pluck('comics.id')->all();

        // Loop through the order and update each comic's position
        foreach ($order as $index => $comicId) {
            if (! in_array((int) $comicId, $memberIds, true)) {
                continue;
            }
            // Assuming you have a many-to-many relationship defined in the Collection model
            $collection->comics()->updateExistingPivot($comicId, ['order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
