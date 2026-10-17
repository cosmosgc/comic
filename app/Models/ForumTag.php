<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Forum-scoped tag (separate from the comic-scoped `tags` table).
 */
class ForumTag extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'use_count'];

    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(ForumThread::class, 'forum_tag_thread')->withTimestamps();
    }

    /**
     * Resolve a list of tag names to ids, creating missing tags and
     * keeping use_count in step.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public static function resolveIds(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $tag = static::firstOrCreate(['slug' => $slug], ['name' => $name]);
            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Sync tags onto a thread, adjusting use_count for the delta.
     *
     * @param  array<int, string>  $names
     */
    public static function syncForThread(ForumThread $thread, array $names): void
    {
        $newIds = static::resolveIds($names);
        $oldIds = $thread->tags()->pluck('forum_tags.id')->all();

        $thread->tags()->sync($newIds);

        foreach (array_diff($oldIds, $newIds) as $removed) {
            static::whereKey($removed)->where('use_count', '>', 0)->decrement('use_count');
        }
        foreach (array_diff($newIds, $oldIds) as $added) {
            static::whereKey($added)->increment('use_count');
        }
    }
}
