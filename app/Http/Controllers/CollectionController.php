<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Comic;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::with('comics')->get(); // Fetch all collections with related comics
        return view('collections.index', compact('collections'));
    }

    public function show(Collection $collection)
    {
        $collection->load('comics'); // Load related comics for the specific collection
        return view('collections.show', compact('collection'));
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
        if ($request->has('search')) {
            $search = $request->input('search');

            // Filter by name or description
            $query->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
        }

        // Get the results
        $collections = $query->with('comics')->get(); // Include comics if needed

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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection = Collection::create($request->only('name', 'description'));

        // Attach comics if any are selected
        if ($request->filled('comics')) {
            $collection->comics()->attach($request->comics);
        }

        return redirect()->route('collections.create')->with('success', 'Collection created successfully.');
    }

    public function edit(Collection $collection)
    {
        $comics = Comic::all(); // Fetch all comics for selection
        $selectedComics = $collection->comics; // Comics already in the collection
        return view('collections.edit', compact('collection', 'comics', 'selectedComics'));
    }

    public function update(Request $request, Collection $collection)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection->update($request->only('name', 'description'));

        // Sync comics with new selections
        $collection->comics()->sync($request->comics);

        return redirect()->route('collections.edit', $collection)->with('success', 'Collection updated successfully.');
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
