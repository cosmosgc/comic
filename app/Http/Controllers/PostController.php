<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with(['author', 'referencedPost.author'])
            ->withCount(['quotes', 'likedByUsers'])
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['data' => $posts]);
        }

        // Single query so the heart button knows the viewer's likes (no N+1).
        $likedPostIds = $request->user()
            ? $request->user()->likedPosts()->pluck('posts.id')->all()
            : [];

        return view('posts.index', compact('posts', 'likedPostIds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string|max:280',
            'media.*' => 'nullable|image|max:4048',
            'referenced_post_id' => 'nullable|exists:posts,id',
        ]);

        // Certifica que pelo menos um dos campos (texto ou mídia) está preenchido
        if (! $request->text && ! $request->hasFile('media') && ! $request->referenced_post_id) {
            return redirect()->back()->withErrors(['error' => 'O post precisa ter texto ou mídia.']);
        }

        $post = Post::create([
            'author_id' => auth()->id(),
            'text' => $request->text,
            'referenced_post_id' => $request->referenced_post_id,
        ]);

        if ($request->hasFile('media')) {
            $directory = public_path('storage/posts_media');

            // Ensure the directory exists
            if (! file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $mediaPaths = [];
            foreach ($request->file('media') as $media) {
                $filename = time().'_'.$media->getClientOriginalName(); // Generate a unique filename
                $media->move($directory, $filename); // Move file to the directory
                $mediaPaths[] = 'storage/posts_media/'.$filename; // Store the relative path
            }

            // NOTE: assign the array, not a JSON string — the
            // Post::$casts['media' => 'array'] handles encoding.
            $post->media = $mediaPaths;
            $post->save();
        }

        return redirect()->route('posts.index');
    }
}
