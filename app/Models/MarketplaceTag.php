<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Marketplace-scoped tag (separate from comic tags and forum tags).
 */
class MarketplaceTag extends Model
{
    protected $fillable = ['name', 'slug', 'use_count'];

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceListing::class, 'marketplace_listing_tag')->withTimestamps();
    }

    /**
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
     * @param  array<int, string>  $names
     */
    public static function syncForListing(MarketplaceListing $listing, array $names): void
    {
        $newIds = static::resolveIds($names);
        $oldIds = $listing->tags()->pluck('marketplace_tags.id')->all();

        $listing->tags()->sync($newIds);

        foreach (array_diff($oldIds, $newIds) as $removed) {
            static::whereKey($removed)->where('use_count', '>', 0)->decrement('use_count');
        }
        foreach (array_diff($newIds, $oldIds) as $added) {
            static::whereKey($added)->increment('use_count');
        }
    }
}
