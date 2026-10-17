<?php

namespace App\Http\Controllers;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumThread;
use App\Models\ForumThreadRead;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Forum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Public, read-mostly forum surface: index, board, thread, search and
 * unread digest. Write actions live in ForumThreadController /
 * ForumPostController.
 */
class ForumController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureEnabled();

        $sections = ForumCategory::query()
            ->visible()
            ->sections()
            ->with([
                'children' => fn ($query) => $query->visible()->withCount([
                    'threads' => fn ($q) => $q->visible(),
                ]),
                'children.threads' => fn ($query) => $query->visible()->pinnedFirst()->limit(1),
                'children.threads.lastPoster',
            ])
            ->orderBy('sort_order')
            ->get();

        $stats = [
            'threads' => Schema::hasTable('forum_threads') ? ForumThread::visible()->count() : 0,
            'posts' => Schema::hasTable('forum_posts') ? ForumPost::count() : 0,
            'members' => User::count(),
            'newest' => User::latest('id')->first(),
        ];

        return view('forum.index', [
            'sections' => $sections,
            'stats' => $stats,
            'showPanels' => false,
        ]);
    }

    public function category(Request $request, string $categorySlug)
    {
        $this->ensureEnabled();
        $category = $this->resolveCategory($categorySlug);

        $validated = $request->validate([
            'sort' => 'nullable|in:latest,newest,replies,reactions,unanswered',
            'filter' => 'nullable|in:mine,unread,solved',
            'tag' => 'nullable|string|max:30',
        ]);
        $sort = $validated['sort'] ?? 'latest';
        $filter = $validated['filter'] ?? null;

        $threads = ForumThread::query()
            ->where('forum_category_id', $category->id)
            ->visible()
            ->with(['author.badges', 'lastPoster', 'tags'])
            ->withCount('posts');

        if ($filter === 'mine' && $request->user()) {
            $threads->where('user_id', $request->user()->id);
        } elseif ($filter === 'solved') {
            $threads->whereNotNull('solved_post_id');
        }

        if (! empty($validated['tag'])) {
            $threads->whereHas('tags', fn ($q) => $q->where('slug', $validated['tag']));
        }

        match ($sort) {
            'newest' => $threads->orderByDesc('created_at'),
            'replies' => $threads->orderByDesc('reply_count')->orderByDesc('last_post_at'),
            'reactions' => $threads->orderByDesc('reaction_count')->orderByDesc('last_post_at'),
            'unanswered' => $threads->orderByDesc('is_pinned')->where('reply_count', 0)->orderByDesc('created_at'),
            default => $threads->pinnedFirst(),
        };

        $threads = $threads->paginate(20)->withQueryString();

        return view('forum.category', [
            'category' => $category,
            'threads' => $threads,
            'sort' => $sort,
            'filter' => $filter,
            'tag' => $validated['tag'] ?? null,
            'canStartThread' => Forum::canStartThread($request->user(), $category),
            'showPanels' => false,
        ]);
    }

    public function thread(Request $request, string $categorySlug, string $threadSlug)
    {
        $this->ensureEnabled();
        $category = $this->resolveCategory($categorySlug);
        $thread = $this->resolveThread($category, $threadSlug, $request->user());

        // Count once per viewer (session), like Comic/Post views.
        $viewed = $request->session()->get('viewed_forum_threads', []);
        if (! in_array($thread->id, $viewed)) {
            $thread->increment('view_count');
            $request->session()->push('viewed_forum_threads', $thread->id);
        }

        $thread->load(['author.badges', 'tags', 'category']);
        $thread->loadCount('posts');

        // Mark read up to the latest visible post.
        if ($request->user() && Schema::hasTable('forum_thread_reads')) {
            $lastId = $thread->posts()->max('id');
            ForumThreadRead::updateOrCreate(
                ['user_id' => $request->user()->id, 'forum_thread_id' => $thread->id],
                ['last_read_post_id' => $lastId, 'last_read_at' => now()]
            );
        }

        $posts = $thread->posts()
            ->visible()
            ->with(['author.badges', 'parent.author', 'attachments', 'reactions'])
            ->withCount('reactions')
            ->paginate((int) config('forum.posts_per_page', 15))
            ->withQueryString();

        // Which reactions the viewer already left (one query, no N+1).
        $viewerReactions = [];
        if ($request->user() && Schema::hasTable('forum_post_reactions')) {
            $ids = $posts->pluck('id')->all();
            $viewerReactions = ForumPostReaction::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('forum_post_id', $ids)
                ->get()
                ->groupBy('forum_post_id')
                ->map(fn ($rows) => $rows->pluck('reaction')->all())
                ->all();
        }

        $subscribed = false;
        if ($request->user() && Schema::hasTable('forum_subscriptions')) {
            $subscribed = $thread->subscriptions()->where('user_id', $request->user()->id)->exists();
        }

        return view('forum.thread', [
            'category' => $category,
            'thread' => $thread,
            'posts' => $posts,
            'viewerReactions' => $viewerReactions,
            'subscribed' => $subscribed,
            'canModerate' => Forum::isModerator($request->user(), $category),
            'isBanned' => Forum::isBanned($request->user(), $category->id),
            'showPanels' => false,
        ]);
    }

    public function search(Request $request)
    {
        $this->ensureEnabled();

        $validated = $request->validate(['q' => 'nullable|string|max:100']);
        $query = trim((string) ($validated['q'] ?? ''));

        $threads = ForumThread::query()
            ->visible()
            ->with(['author.badges', 'category', 'tags'])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('title', 'like', '%'.$query.'%')
                        ->orWhereHas('posts', fn ($p) => $p->where('body', 'like', '%'.$query.'%'))
                        ->orWhereHas('author', fn ($a) => $a->where('name', 'like', '%'.$query.'%'))
                        ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', '%'.$query.'%'));
                });
            })
            ->orderByDesc('last_post_at')
            ->paginate(20)
            ->withQueryString();

        if ($query !== '') {
            ActivityLogger::log('search.posts', [
                'user' => $request->user(),
                'description' => 'Buscou no fórum por "'.$query.'"',
                'url' => $request->fullUrl(),
                'metadata' => ['q' => $query, 'scope' => 'forum', 'results' => $threads->total()],
                'ip' => $request->ip(),
            ]);
        }

        return view('forum.search', [
            'threads' => $threads,
            'query' => $query,
            'showPanels' => false,
        ]);
    }

    public function unread(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();

        $threads = ForumThread::query()
            ->visible()
            ->with(['author', 'category'])
            ->whereHas('posts', fn ($q) => $q->where('position', '>', 1))
            ->orderByDesc('last_post_at')
            ->get()
            ->filter(fn (ForumThread $thread) => $thread->isUnreadFor($user))
            ->values();

        return view('forum.unread', [
            'threads' => $threads,
            'showPanels' => false,
        ]);
    }

    protected function ensureEnabled(): void
    {
        abort_unless(Forum::enabled(), 404);
    }

    protected function resolveCategory(string $slug): ForumCategory
    {
        return ForumCategory::query()
            ->visible()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    protected function resolveThread(ForumCategory $category, string $slug, $user): ForumThread
    {
        $thread = ForumThread::query()
            ->where('forum_category_id', $category->id)
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $thread->is_approved && ! Forum::isModerator($user, $category)) {
            abort(404);
        }

        return $thread;
    }
}
