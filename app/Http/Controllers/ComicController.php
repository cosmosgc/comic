<?php

namespace App\Http\Controllers;
use App\Models\Comic;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComicController extends Controller
{
    public function index() {
        $comics = Comic::paginate(10);
        return view('comics.index', compact('comics'));
    }
    public function create()
    {
        return view('comics.upload');
    }

    public function show($id)
    {
        // Fetch the comic by its ID

        $comic = Comic::with('pages')->findOrFail($id);
        // Return a view that displays the comic and its pages
        return view('comics.show', compact('comic'));
    }


    public function store(Request $request)
    {
        //dd($request);
        $request->validate([
            'title' => 'required|string|max:255',
            'folder' => 'required',
        ]);

        // Create a new comic
        $comic = Comic::create([
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'description' => $request->input('desc'),
            // Add other fields as needed (like author, description, etc.)
        ]);

        // Define the comic folder path
        $comicFolderPath = "comics/{$comic->id}";

        // Check if the folder exists, if not create it
        if (!Storage::disk('public')->exists($comicFolderPath)) {
            Storage::disk('public')->makeDirectory($comicFolderPath);
        }
        $firstImagePath = null;
        // Handle each image in the folder
        if ($request->hasFile('folder')) {
            $pageNumber = 1;

            foreach ($request->file('folder') as $file) {
                if ($file->isValid()) {

                    $originalFileName = $file->getClientOriginalName();
                    // Store the image in the folder for the comic
                    $filePath = $file->storeAs($comicFolderPath, $originalFileName,'public');
                    if ($pageNumber === 1) {
                        $firstImagePath = $filePath;
                    }
                    // Create a new Page entry for each image
                    Page::create([
                        'comic_id' => $comic->id,
                        'image_path' => $filePath,
                        'page_number' => $pageNumber,
                    ]);

                    $pageNumber++;
                }
            }
        }
        if ($firstImagePath) {
            $comic->update(['image_path' => $firstImagePath]);
        }
        //dd($request, $comicFolderPath);
        return redirect()->route('comics.show', $comic->id)->with('success', 'Comic uploaded successfully.');
    }

}
