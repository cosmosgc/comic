<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Public hashtag timeline: every post carrying #tag.
 */
class HashtagController extends Controller
{
    public function show(Request $request, string $tag)
    {
        $tag = ltrim(mb_strtolower(trim($tag)), '#');

        if (! Schema::hasTable('hashtags') || $tag === '') {
            return redirect()->route('posts.index');
        }

        $hashtag = Hashtag::where('tag', $tag)->firstOrFail();

        $counts = ['quotes'];
        if (Schema::hasColumn('posts', 'parent_id')) {
            $counts[] = 'replies';
        }
        if (Schema::hasTable('post_likes')) {
            $counts[] = 'likedByUsers';
        }

        $posts = $hashtag->posts()
            ->with(['author.badges', 'referencedPost.author.badges'])
            ->withCount($counts)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $likedPostIds = $request->user()
            ? $request->user()->likedPosts()->pluck('posts.id')->all()
            : [];
        $bookmarkedPostIds = $request->user() && Schema::hasTable('bookmarks')
            ? $request->user()->bookmarkedPosts()->pluck('posts.id')->all()
            : [];

        return view('hashtags.show', compact('hashtag', 'posts', 'likedPostIds', 'bookmarkedPostIds'));
    }
}
