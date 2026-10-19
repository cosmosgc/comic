<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Hashtag;
use App\Models\Post;
use App\Support\ActivityLogger;
use App\Support\ImageStore;
use App\Support\SocialNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'tab' => 'nullable|in:latest,trending,following',
        ]);
        $tab = $validated['tab'] ?? 'latest';
        $query = trim($validated['q'] ?? '');

        $posts = Post::query()
            ->notMutedBy($request->user())
            ->with(['author.badges', 'referencedPost.author.badges'])
            ->withCount(['quotes', 'likedByUsers', 'replies']);

        if ($query !== '') {
            $posts->where(function ($q) use ($query) {
                $q->where('text', 'like', '%'.$query.'%')
                    ->orWhereHas('author', fn ($a) => $a->where('name', 'like', '%'.$query.'%'));
            });
        }

        if ($tab === 'following') {
            $followingIds = [];
            if ($request->user() && Schema::hasTable('follows')) {
                $followingIds = DB::table('follows')
                    ->where('follower_id', $request->user()->id)
                    ->pluck('followed_id')
                    ->all();
            }
            $posts->whereIn('posts.author_id', $followingIds)->latest();
        } elseif ($tab === 'trending') {
            // Engagement velocity: likes + 2x replies over the last 7 days.
            $posts->where('posts.created_at', '>=', now()->subDays(7))
                ->orderByRaw('(liked_by_users_count + replies_count * 2) DESC')
                ->orderByDesc('posts.created_at');
        } else {
            $posts->latest();
        }

        $posts = $posts->paginate(20)->withQueryString();

        if ($query !== '') {
            ActivityLogger::log('search.posts', [
                'user' => $request->user(),
                'description' => 'Buscou posts por "'.$query.'"',
                'url' => $request->fullUrl(),
                'metadata' => ['q' => $query, 'tab' => $tab, 'results' => $posts->total()],
                'ip' => $request->ip(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $posts]);
        }

        // Single query so the heart button knows the viewer's likes (no N+1).
        $likedPostIds = $request->user()
            ? $request->user()->likedPosts()->pluck('posts.id')->all()
            : [];

        // Bookmarks are private: only the viewer's own saved posts.
        $bookmarkedPostIds = [];
        if ($request->user() && Schema::hasTable('bookmarks')) {
            $bookmarkedPostIds = $request->user()->bookmarkedPosts()->pluck('posts.id')->all();
        }

        return view('posts.index', compact('posts', 'likedPostIds', 'bookmarkedPostIds', 'tab', 'query'));
    }

    public function store(StorePostRequest $request)
    {
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
            $mediaPaths = [];
            foreach ($request->file('media') as $media) {
                if (! $media->isValid()) {
                    continue;
                }

                // Generated name + MIME-derived extension (see ImageStore).
                $mediaPaths[] = ImageStore::put($media, 'posts_media');
            }

            // NOTE: assign the array, not a JSON string — the
            // Post::$casts['media' => 'array'] handles encoding.
            $post->media = $mediaPaths;
            $post->save();
        }

        ActivityLogger::log($post->parent_id ? 'post.replied' : 'post.created', [
            'user' => $request->user(),
            'subject' => $post,
            'description' => ($post->parent_id ? 'Respondeu na thread #'.$post->parent_id : 'Criou o post #'.$post->id)
                .($post->text ? ' — '.ActivityLogger::excerpt($post->text) : ''),
            'metadata' => [
                'post_id' => $post->id,
                'parent_id' => $post->parent_id,
                'referenced_post_id' => $post->referenced_post_id,
                'has_media' => ! empty($post->media),
            ],
            'ip' => $request->ip(),
        ]);

        // Parse #hashtags for the timeline + trending, then fan out
        // @mention / reply / quote notifications through the bell.
        Hashtag::syncForPost($post, $post->text);
        SocialNotifier::postCreated($post);

        if ($post->parent_id) {
            return redirect()->route('posts.show', $post->parent_id);
        }

        return redirect()->route('posts.index');
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'text' => 'nullable|string|max:280',
        ]);

        $post->fill(['text' => $validated['text'] ?? null]);
        if ($post->isDirty('text')) {
            $post->edited_at = now();
        }
        $post->save();
        Hashtag::syncForPost($post, $post->text);

        return redirect()->route('posts.show', $post)->with('success', 'Post atualizado.');
    }

    /**
     * Delete a post. Replies and quotes cascade at the database level;
     * the media files on disk are removed here.
     */
    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        foreach ((array) $post->media as $media) {
            ImageStore::delete($media);
        }

        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Post excluído.');
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

        $post->load(['author.badges', 'referencedPost.author.badges', 'parent.author.badges']);

        // Counts live here (not only in the feed) so this page works
        // whether or not the likes feature is merged.
        $counts = ['quotes', 'replies'];
        if (Schema::hasTable('post_likes')) {
            $counts[] = 'likedByUsers';
        }
        $post->loadCount($counts);

        $with = ['author.badges', 'referencedPost.author.badges'];
        $replyCounts = ['quotes', 'replies'];
        if (Schema::hasTable('post_likes')) {
            $replyCounts[] = 'likedByUsers';
        }

        $replies = $post->replies()
            ->notMutedBy($request->user())
            ->with($with)
            ->withCount($replyCounts)
            ->latest()
            ->paginate(20);

        // Nested levels for the Reddit-style tree (bounded: 3 full levels,
        // then id-only stubs so deeper chains render a "continue" link).
        // MySQL 5.7 hosts can't do recursive CTEs, hence fixed depth.
        $replies->getCollection()->load([
            'replies' => fn ($query) => $query->notMutedBy($request->user())->with($with)->withCount($replyCounts)->latest(),
            'replies.replies' => fn ($query) => $query->notMutedBy($request->user())->with($with)->withCount($replyCounts)->latest(),
            'replies.replies.replies:id,parent_id',
        ]);

        // Ancestor chain, oldest first. Depth-guarded (corrupt data) and
        // deliberately one primary-key lookup per level, capped at 20:
        // MySQL 5.7 hosts cannot use a recursive CTE, and each ancestor
        // renders the full post partial, so batching by id would still
        // cost one query per level.
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

        $bookmarkedPostIds = [];
        if ($request->user() && Schema::hasTable('bookmarks')) {
            $bookmarkedPostIds = $request->user()->bookmarkedPosts()->pluck('posts.id')->all();
        }

        return view('posts.show', compact('post', 'replies', 'ancestors', 'likedPostIds', 'bookmarkedPostIds'));
    }
}
