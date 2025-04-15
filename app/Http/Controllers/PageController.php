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
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $comic = Comic::findOrFail($comicId);

        // Get uploaded file
        $file = $request->file('image');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Define destination
        $destinationPath = public_path("storage/comics/{$comic->id}/pages");

        // Ensure directory exists
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // Build filename and check for duplicates
        $filename = "{$originalName}.{$extension}";
        $fullPath = "{$destinationPath}/{$filename}";

        if (file_exists($fullPath)) {
            $hash = substr(md5(now() . rand()), 0, 8);
            $filename = "{$originalName}_{$hash}.{$extension}";
        }

        // Move the file
        $file->move($destinationPath, $filename);

        // Get page number or calculate it
        $inputPageNumber = $request->input('page_number');
        $pageNumber = (is_numeric($inputPageNumber) && (int)$inputPageNumber > 0)
            ? (int)$inputPageNumber
            : $comic->pages()->max('page_number') + 1;

        // Save relative path
        $relativePath = "comics/{$comic->id}/pages/{$filename}";

        // Create Page entry
        Page::create([
            'comic_id' => $comic->id,
            'image_path' => $relativePath,
            'page_number' => $pageNumber,
        ]);

        return redirect()->route('comics.show', $comic->id)->with('success', 'Page uploaded successfully.');
    }

    public function addPage(Request $request, $comicId)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $comic = Comic::findOrFail($comicId);
        $pageNumber = $comic->pages()->count() + 1;

        // Get uploaded file
        $file = $request->file('image');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Define destination
        $destinationPath = public_path("storage/comics/{$comic->id}");

        // Ensure directory exists
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // Final filename
        $filename = "{$originalName}.{$extension}";
        $fullPath = "{$destinationPath}/{$filename}";

        // If file exists, add a hash
        if (file_exists($fullPath)) {
            $hash = substr(md5(now() . rand()), 0, 8);
            $filename = "{$originalName}_{$hash}.{$extension}";
        }

        // Move the file
        $file->move($destinationPath, $filename);

        // Relative path for storage
        $relativePath = "comics/{$comic->id}/{$filename}";

        // Create page entry
        Page::create([
            'comic_id' => $comic->id,
            'image_path' => $relativePath,
            'page_number' => $pageNumber,
        ]);

        return redirect()->route('comics.edit', $comic->id)->with('success', 'Page uploaded successfully.');
    }

    public function getPagesByComicId($id) {
        // Fetch the pages related to the comic
        $pages = Page::where('comic_id', $id)->orderBy('page_number')->get();

        // Return the pages as JSON
        return response()->json($pages);
    }
}
