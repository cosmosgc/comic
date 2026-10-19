<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageRequest;
use App\Http\Resources\PageResource;
use App\Models\Comic;
use App\Models\Page;
use App\Support\ImageStore;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function create($comicId)
    {
        $comic = Comic::findOrFail($comicId);

        return view('pages.create', compact('comic'));
    }

    public function store(StorePageRequest $request, $comicId)
    {
        $comic = Comic::findOrFail($comicId);
        $this->authorize('update', $comic);

        // Store with a generated name (never the client's) under the
        // comic's `pages/` folder.
        $relativePath = ImageStore::putInStorage($request->file('image'), "comics/{$comic->id}/pages");

        // Get page number or calculate it
        $inputPageNumber = $request->input('page_number');
        $pageNumber = (is_numeric($inputPageNumber) && (int) $inputPageNumber > 0)
            ? (int) $inputPageNumber
            : $comic->pages()->max('page_number') + 1;

        // Create Page entry
        Page::create([
            'comic_id' => $comic->id,
            'image_path' => $relativePath,
            'page_number' => $pageNumber,
        ]);

        // NOTE: route is `comics.showById` — there is no `comics.show` route.
        return redirect()->route('comics.showById', $comic->id)->with('success', 'Página enviada com sucesso.');
    }

    public function addPage(StorePageRequest $request, $comicId)
    {
        $comic = Comic::findOrFail($comicId);
        $this->authorize('update', $comic);
        $pageNumber = $comic->pages()->count() + 1;

        // Generated name, comic root (same convention as comic uploads).
        $relativePath = ImageStore::putInStorage($request->file('image'), "comics/{$comic->id}");

        // Create page entry
        Page::create([
            'comic_id' => $comic->id,
            'image_path' => $relativePath,
            'page_number' => $pageNumber,
        ]);

        return redirect()->route('comics.edit', $comic->id)->with('success', 'Página enviada com sucesso.');
    }

    public function getPagesByComicId($id)
    {
        // Pages are the comic's content: a scheduled/unlisted comic must
        // not leak them to anyone who guesses the id.
        $comic = Comic::findOrFail($id);
        abort_unless($comic->isVisibleTo(Auth::user()), 404);

        // Fetch the pages related to the comic
        $pages = $comic->pages()->orderBy('page_number')->get();

        // Return the pages as JSON
        return response()->json(PageResource::collection($pages));
    }
}
