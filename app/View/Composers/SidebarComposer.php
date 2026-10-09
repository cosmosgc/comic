<?php

namespace App\View\Composers;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\Widget;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SidebarComposer
{
    public const POPULAR_TAGS_LIMIT = 10;

    public const SIDEBAR_COMICS_LIMIT = 5;

    /**
     * Share sidebar data with the panel partials so every page
     * using the panels gets real content instead of placeholders.
     */
    public function compose(View $view): void
    {
        $view->with('widgets', $view->offsetExists('widgets') ? $view->offsetGet('widgets') : Widget::all());
        $view->with('tags', $view->offsetExists('tags') ? $view->offsetGet('tags') : self::popularTags());
        $view->with('latestComics', $view->offsetExists('latestComics') ? $view->offsetGet('latestComics') : self::latestUploads());
        $view->with('recommendedComics', $view->offsetExists('recommendedComics') ? $view->offsetGet('recommendedComics') : self::recommended());
    }

    /**
     * Popular tags driven by the comic like system.
     *
     * A randomized threshold keeps the list fresh on every load:
     * only tags whose total comic likes meet the threshold qualify,
     * ordered by total likes, then shuffled within the top pool.
     */
    public static function popularTags(int $limit = self::POPULAR_TAGS_LIMIT)
    {
        $hasLikes = Schema::hasTable('comic_user_likes');

        if (! Schema::hasTable('tags')) {
            return collect();
        }

        // Total likes across all comics tagged with each tag.
        $query = Tag::query()
            ->select('tags.*')
            ->withCount('comics as comics_count');

        if ($hasLikes) {
            $query->selectSub(
                'SELECT COUNT(*) FROM comic_user_likes '.
                'INNER JOIN comic_tag AS ct_likes ON ct_likes.comic_id = comic_user_likes.comic_id '.
                'WHERE ct_likes.tag_id = tags.id',
                'likes_sum'
            );
        }

        $candidates = $query
            ->orderByDesc($hasLikes ? 'likes_sum' : 'comics_count')
            ->orderBy('name')
            ->limit(max($limit * 2, $limit))
            ->get();

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
     * Newest comics first.
     */
    public static function latestUploads(int $limit = self::SIDEBAR_COMICS_LIMIT)
    {
        $query = Comic::query()->withCount(self::engagementCounts())->latest();

        return $query->limit($limit)->get();
    }

    /**
     * Like-driven recommendations with a randomized threshold.
     *
     * Comics need at least <threshold> likes to enter the pool, then
     * a random pick of 5 keeps the sidebar from going stale. Falls
     * back to latest uploads when nothing qualifies yet.
     */
    public static function recommended(int $limit = self::SIDEBAR_COMICS_LIMIT)
    {
        $hasLikes = Schema::hasTable('comic_user_likes');
        $counts = self::engagementCounts();

        if (! $hasLikes) {
            return self::latestUploads($limit);
        }

        $maxLikes = (int) (Comic::withCount('likedByUsers as likes')
            ->orderByDesc('likes')
            ->first()?->likes ?? 0);

        // Random bar between 0 and the top like count (capped so
        // fresh sites with huge outliers still show variety).
        $threshold = $maxLikes > 0 ? random_int(0, min($maxLikes, 5)) : 0;

        $pool = Comic::query()
            ->withCount($counts)
            ->orderByDesc('liked_by_users_count')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->filter(fn ($comic) => (int) ($comic->liked_by_users_count ?? 0) >= $threshold)
            ->values();

        if ($pool->isEmpty()) {
            return self::latestUploads($limit);
        }

        if ($pool->count() < $limit) {
            $fallback = Comic::query()
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
