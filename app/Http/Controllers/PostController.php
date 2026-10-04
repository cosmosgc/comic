<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with(['author', 'referencedPost.author'])
            ->withCount(['quotes', 'replies'])
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json(['data' => $posts]);
        }

        return view('posts.index', compact('posts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string|max:280',
            'media.*' => 'nullable|image|max:4048',
            'referenced_post_id' => 'nullable|exists:posts,id',
            'parent_id' => 'nullable|exists:posts,id',
        ]);

        // Certifica que pelo menos um dos campos (texto ou mídia) está preenchido
        if (! $request->text && ! $request->hasFile('media') && ! $request->referenced_post_id) {
            return redirect()->back()->withErrors(['error' => 'O post precisa ter texto ou mídia.']);
        }

        $post = Post::create([
            'author_id' => auth()->id(),
            'text' => $request->text,
            'referenced_post_id' => $request->referenced_post_id,
            'parent_id' => $request->parent_id,
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

        if ($post->parent_id) {
            return redirect()->route('posts.show', $post->parent_id);
        }

        return redirect()->route('posts.index');
    }

    /**
     * Single-post thread view: parent chain plus direct replies.
     */
    public function show(Request $request, Post $post)
    {
        // Count once per viewer (session), like Comic::$view_count per view.
        $viewed = $request->session()->get('viewed_posts', []);
        if (! in_array($post->id, $viewed)) {
            $post->increment('view_count');
            $request->session()->push('viewed_posts', $post->id);
        }

        $post->load(['author', 'referencedPost.author', 'parent.author']);

        // Counts live here (not only in the feed) so this page works
        // whether or not the likes feature is merged.
        $counts = ['quotes', 'replies'];
        if (Schema::hasTable('post_likes')) {
            $counts[] = 'likedByUsers';
        }
        $post->loadCount($counts);

        $replies = $post->replies()
            ->with(['author', 'referencedPost.author'])
            ->withCount('quotes')
            ->paginate(20);

        // Ancestor chain, oldest first (depth-guarded against corrupt data).
        $ancestors = collect();
        $cursor = $post->parent;
        while ($cursor && $ancestors->count() < 20) {
            $ancestors->prepend($cursor);
            $cursor = $cursor->parent;
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $post, 'replies' => $replies]);
        }

        // Raw query (not the likedPosts relation) so this page renders
        // with or without the likes migration merged.
        $likedPostIds = [];
        if ($request->user() && Schema::hasTable('post_likes')) {
            $likedPostIds = DB::table('post_likes')
                ->where('user_id', $request->user()->id)
                ->pluck('post_id')
                ->all();
        }

        return view('posts.show', compact('post', 'replies', 'ancestors', 'likedPostIds'));
    }
}
