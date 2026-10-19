<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Private per-user post bookmarks ("salvos").
 */
class BookmarkController extends Controller
{
    public function toggle(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if (! Schema::hasTable('bookmarks')) {
            return response()->json(['bookmarked' => false]);
        }

        if ($user->bookmarkedPosts()->whereKey($post->getKey())->exists()) {
            $user->bookmarkedPosts()->detach($post->getKey());
            $bookmarked = false;
        } else {
            $user->bookmarkedPosts()->attach($post->getKey());
            $bookmarked = true;

            ActivityLogger::log('post.bookmarked', [
                'user' => $user,
                'subject' => $post,
                'description' => 'Salvou o post #'.$post->id,
                'metadata' => ['post_id' => $post->id],
                'ip' => $request->ip(),
            ]);
        }

        return response()->json(['bookmarked' => $bookmarked]);
    }

    /**
     * The viewer's private bookmarks page.
     */
    public function index(Request $request): View
    {
        if (! Schema::hasTable('bookmarks')) {
            return view('bookmarks.index', ['posts' => null, 'likedPostIds' => [], 'bookmarkedPostIds' => []]);
        }

        $user = $request->user();
        $counts = ['quotes'];
        if (Schema::hasColumn('posts', 'parent_id')) {
            $counts[] = 'replies';
        }
        if (Schema::hasTable('post_likes')) {
            $counts[] = 'likedByUsers';
        }

        $posts = $user->bookmarkedPosts()
            ->with(['author.badges', 'referencedPost.author.badges'])
            ->withCount($counts)
            ->orderByPivot('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $likedPostIds = $user->likedPosts()->pluck('posts.id')->all();
        $bookmarkedPostIds = $user->bookmarkedPosts()->pluck('posts.id')->all();

        return view('bookmarks.index', compact('posts', 'likedPostIds', 'bookmarkedPostIds'));
    }
}
