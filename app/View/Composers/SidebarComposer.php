<?php

namespace App\View\Composers;

use App\Models\Comic;
use App\Models\Hashtag;
use App\Models\Tag;
use App\Models\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SidebarComposer
{
    public const POPULAR_TAGS_LIMIT = 10;

    public const SIDEBAR_COMICS_LIMIT = 5;

    /** Request attribute holding the sidebar data computed so far. */
    protected const CACHE_KEY = 'sidebar_panel_data';

    /**
     * Share sidebar data with the panel partials so every page
     * using the panels gets real content instead of placeholders.
     *
     * Both panels (left and right) are composed separately, and the
     * panels are used on every page, so the data is computed once and
     * memoized on the current request — a page without controller
     * provided values would otherwise run the whole sidebar query set
     * twice. Values already provided by the controller still win.
     */
    public function compose(View $view): void
    {
        $request = request();
        $shared = $request->attributes->get(self::CACHE_KEY) ?? [];

        foreach (['widgets', 'tags', 'upcomingComics', 'latestComics', 'recommendedComics', 'trendingHashtags'] as $key) {
            if ($view->offsetExists($key)) {
                continue;
            }

            $shared[$key] ??= $this->resolve($key);
            $view->with($key, $shared[$key]);
        }

        $request->attributes->set(self::CACHE_KEY, $shared);
    }

    /**
     * Sidebar data for one key (see compose()).
     */
    protected function resolve(string $key): Collection
    {
        return match ($key) {
            'widgets' => Widget::all(),
            'tags' => self::popularTags(),
            'upcomingComics' => self::upcoming(),
            'latestComics' => self::latestUploads(),
            'recommendedComics' => self::recommended(),
            'trendingHashtags' => Hashtag::trending(),
        };
    }

    /**
     * Popular tags driven by the comic like system.
     *
     * A randomized threshold keeps the list fresh on every load:
     * only tags whose total comic likes meet the threshold qualify,
     * ordered by total likes, then shuffled within the top pool.
     */
    public static function popularTags(int $limit = self::POPULAR_TAGS_LIMIT): Collection
    {
        $hasLikes = Schema::hasTable('comic_user_likes');

        if (! Schema::hasTable('tags')) {
            return collect();
        }

        // Total likes across all published comics tagged with each tag.
        $query = Tag::query()
            ->select('tags.*')
            ->withCount(['comics as comics_count' => fn ($q) => $q->published()]);

        if ($hasLikes) {
            $likesSub = 'SELECT COUNT(*) FROM comic_user_likes '.
                'INNER JOIN comic_tag AS ct_likes ON ct_likes.comic_id = comic_user_likes.comic_id '.
                'WHERE ct_likes.tag_id = tags.id';
            if (Schema::hasColumn('comics', 'published_at')) {
                $likesSub .= ' AND EXISTS (SELECT 1 FROM comics WHERE comics.id = ct_likes.comic_id '.
                    'AND (comics.published_at IS NULL OR comics.published_at <= CURRENT_TIMESTAMP))';
            }
            $query->selectSub($likesSub, 'likes_sum');
        }

        // Hidden categories never surface in the sidebar; their tags stay
        // stored and searchable. Skipped on hosts whose migrations lag
        // (no is_visible column yet).
        if (Schema::hasColumn('tag_categories', 'is_visible')) {
            $query->where(function ($inner) {
                $inner->whereNull('tags.category_id')
                    ->orWhereHas('category', fn ($c) => $c->where('is_visible', true));
            });
        }

        $candidates = $query
            ->orderByDesc($hasLikes ? 'likes_sum' : 'comics_count')
            ->orderBy('name')
            ->limit(max($limit * 3, $limit))
            ->get()
            ->filter(fn ($tag) => $tag->displayCategory?->is_visible ?? true)
            ->values();

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        if (! $hasLikes) {
            return $candidates->take($limit)->values();
        }

        // Randomize the popularity bar: tags must reach a random
        // like total to qualify, so mid-tier tags rotate in.
        $maxLikes = (int) $candidates->max('likes_sum');
        $threshold = $maxLikes > 0 ? random_int(0, min($maxLikes, 5)) : 0;

        $qualified = $candidates->filter(
            fn ($tag) => (int) ($tag->likes_sum ?? 0) >= $threshold
        );

        if ($qualified->isEmpty()) {
            $qualified = $candidates;
        }

        return $qualified->shuffle()->take($limit)->values();
    }

    /**
     * Newest published comics first.
     */
    public static function latestUploads(int $limit = self::SIDEBAR_COMICS_LIMIT): Collection
    {
        $query = Comic::query()->published()->withCount(self::engagementCounts())->latest();

        return $query->limit($limit)->get();
    }

    /**
     * The next releases in timeline order. The panel view uses Comic's
     * isFarAhead() rule so it stays in lockstep with the public calendar.
     */
    public static function upcoming(int $limit = self::SIDEBAR_COMICS_LIMIT): Collection
    {
        return Comic::query()
            ->scheduled()
            ->orderBy('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Like-driven recommendations with a randomized threshold.
     *
     * Comics need at least <threshold> likes to enter the pool, then
     * a random pick of 5 keeps the sidebar from going stale. Falls
     * back to latest uploads when nothing qualifies yet.
     */
    public static function recommended(int $limit = self::SIDEBAR_COMICS_LIMIT): Collection
    {
        $hasLikes = Schema::hasTable('comic_user_likes');
        $counts = self::engagementCounts();

        if (! $hasLikes) {
            return self::latestUploads($limit);
        }

        $maxLikes = (int) (Comic::published()->withCount('likedByUsers as likes')
            ->orderByDesc('likes')
            ->first()?->likes ?? 0);

        // Random bar between 0 and the top like count (capped so
        // fresh sites with huge outliers still show variety).
        $threshold = $maxLikes > 0 ? random_int(0, min($maxLikes, 5)) : 0;

        $pool = Comic::query()->published()
            ->withCount($counts)
            ->orderByDesc('liked_by_users_count')
            ->orderByReleaseDate()
            ->limit(20)
            ->get()
            ->filter(fn ($comic) => (int) ($comic->liked_by_users_count ?? 0) >= $threshold)
            ->values();

        if ($pool->isEmpty()) {
            return self::latestUploads($limit);
        }

        if ($pool->count() < $limit) {
            $fallback = Comic::query()->published()
                ->withCount($counts)
                ->whereNotIn('id', $pool->pluck('id'))
                ->inRandomOrder()
                ->limit($limit - $pool->count())
                ->get();

            $pool = $pool->concat($fallback);
        }

        return $pool->shuffle()->take($limit)->values();
    }

    /**
     * @return array<int, string>
     */
    protected static function engagementCounts(): array
    {
        $counts = ['collections'];
        if (Schema::hasTable('comments')) {
            $counts[] = 'comments';
        }
        if (Schema::hasTable('comic_user_likes')) {
            $counts[] = 'likedByUsers';
        }

        return $counts;
    }
}
