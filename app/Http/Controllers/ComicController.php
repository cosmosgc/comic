<?php

namespace App\Http\Controllers;
use App\Models\Comic;
use App\Models\Page;
use App\Models\tag;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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
        $comic = Comic::findOrFail($id);
        return view('comics.show', compact('comic'));
    }

    public function showById($id)
    {
        $comic = Comic::findOrFail($id);
        return view('comics.show', compact('comic'));
    }

    // Method to show a comic by its slug
    public function showBySlug($slug)
    {
        $comic = Comic::where('slug', $slug)->firstOrFail();
        return view('comics.show', compact('comic'));
    }

    public function getComic($id)
    {
        // Fetch the comic along with its related pages
        $comic = Comic::with('pages')->findOrFail($id);

        // Return the comic and its pages as a single JSON response
        return response()->json($comic);
    }

    public function getAllComics(Request $request)
    {
        $query = Comic::query();

        // Check if there is a search query
        if ($request->has('search')) {
            $search = $request->input('search');

            // Filter by title or author
            $query->where('title', 'like', '%' . $search . '%')
                  ->orWhere('author', 'like', '%' . $search . '%');
        }
        if ($request->has('tag')) {
            $tag = Tag::where('name', $request->input('tag'))->first();
            if ($tag) {
                $comics = $tag->comics()->get();
            }
        }


        // Get the results
        $comics = $query->get();

        // Return the comics as a JSON response
        return response()->json($comics);
    }





    public function store(Request $request)
    {
        //dd($request);
        $request->validate([
            'title' => 'required|string|max:255',
            'folder' => 'required',
        ]);

        // Create a new comic
        $slug = $request->input('slug') ?: Comic::generateUniqueSlug($request->input('title'));
        //dd($slug);
        // Create the comic
        $comic = Comic::create([
            'title' => $request->input('title'),
            'author' => $request->input('author'),
            'description' => $request->input('desc'),
            'slug' => $slug, // Store the slug
            'user_id' => Auth::id(), // Associate with the authenticated user
        ]);
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
        return redirect()->route('comics.showBySlug', $comic->slug)->with('success', 'Comic uploaded successfully.');
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
                $slug = $originalSlug . '-' . $count;
                $count++;
            }

            // Update the comic with the new slug
            $comic->slug = $slug;
            $comic->save();
        }

        return response()->json(['message' => 'Slugs updated for all comics without a slug.']);
    }

}
