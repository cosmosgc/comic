<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A #hashtag parsed from post text. The post pivot plus a denormalized
 * use_count power the /hashtag/{tag} timeline and the trending sidebar.
 */
class Hashtag extends Model
{
    protected $fillable = ['tag', 'use_count'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'hashtag_post')->withTimestamps();
    }

    /**
     * Normalized hashtag tokens found in text (no leading #, lowercased).
     *
     * @return array<int, string>
     */
    public static function extractTags(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        preg_match_all('/(?<![A-Za-z0-9_])#([A-Za-z0-9_]{1,80})/', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $tag) => Str::lower($tag),
            $matches[1]
        )));
    }

    /**
     * Replace a post's hashtags with the ones in $text and keep the
     * counters consistent. No-ops where the tables are missing.
     */
    public static function syncForPost(Post $post, ?string $text): void
    {
        if (! Schema::hasTable('hashtags') || ! Schema::hasTable('hashtag_post')) {
            return;
        }

        $tags = self::extractTags($text);
        $ids = [];

        foreach ($tags as $tag) {
            $ids[] = self::firstOrCreate(['tag' => $tag])->id;
        }

        $post->hashtags()->sync($ids);
        $post->hashtags()->get()->each(fn (Hashtag $hashtag) => self::refreshCount($hashtag));
    }

    public static function refreshCount(Hashtag $hashtag): void
    {
        $hashtag->use_count = $hashtag->posts()->count();
        $hashtag->save();
    }

    /**
     * Tags used in the last $days, most used first.
     *
     * @return Collection<int, Hashtag>
     */
    public static function trending(int $days = 7, int $limit = 8)
    {
        if (! Schema::hasTable('hashtags') || ! Schema::hasTable('hashtag_post')) {
            return collect();
        }

        return static::query()
            ->whereHas('posts', fn ($q) => $q->where('posts.created_at', '>=', now()->subDays($days)))
            ->withCount(['posts as recent_count' => fn ($q) => $q->where('posts.created_at', '>=', now()->subDays($days))])
            ->orderByDesc('recent_count')
            ->orderByDesc('use_count')
            ->limit($limit)
            ->get();
    }
}
