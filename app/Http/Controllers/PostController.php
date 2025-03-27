<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with(['author', 'referencedPost'])->latest()->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['data' => $posts]);
        }

        return view('posts.index', compact('posts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string|max:280',
            'media.*' => 'nullable|image|max:4048'
        ]);

        // Certifica que pelo menos um dos campos (texto ou mídia) está preenchido
        if (!$request->text && !$request->hasFile('media')) {
            return redirect()->back()->withErrors(['error' => 'O post precisa ter texto ou mídia.']);
        }

        $post = Post::create([
            'author_id' => auth()->id(),
            'text' => $request->text
        ]);

        if ($request->hasFile('media')) {
            $directory = public_path('storage/posts_media');
        
            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }
        
            $mediaPaths = [];
            foreach ($request->file('media') as $media) {
                $filename = time() . '_' . $media->getClientOriginalName(); // Generate a unique filename
                $media->move($directory, $filename); // Move file to the directory
                $mediaPaths[] = 'storage/posts_media/' . $filename; // Store the relative path
            }
        
            $post->media = json_encode($mediaPaths);
            $post->save();
        }
            

        return redirect()->route('posts.index');
    }

    
}