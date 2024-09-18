<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\Comic;

class PageController extends Controller
{
    public function create($comicId)
    {
        $comic = Comic::findOrFail($comicId);
        return view('pages.create', compact('comic'));
    }

    public function store(Request $request, $comicId)
    {
        $request->validate([
            'image' => 'required|image',
            'page_number' => 'required|integer',
        ]);

        $comic = Comic::findOrFail($comicId);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('comics/pages');
        }

        Page::create([
            'comic_id' => $comic->id,
            'image_path' => $path,
            'page_number' => $request->input('page_number'),
        ]);

        return redirect()->route('comics.show', $comic->id);
    }

    public function getPagesByComicId($id) {
        // Fetch the pages related to the comic
        $pages = Page::where('comic_id', $id)->orderBy('page_number')->get();

        // Return the pages as JSON
        return response()->json($pages);
    }
}
