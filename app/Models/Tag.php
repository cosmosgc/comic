<?php

namespace App\Models;

use App\Services\TagCategorizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
    ];

    /**
     * Categories ride along everywhere so pills never add queries.
     *
     * @var array<int, string>
     */
    protected $with = ['category'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'category_id');
    }

    /**
     * Category for display: the stored one, or the cached "general"
     * fallback so legacy tags are never rendered unstyled.
     */
    public function getDisplayCategoryAttribute(): ?TagCategory
    {
        return $this->category ?? TagCategory::fallback();
    }

    // Define relationship with Comic
    public function comics()
    {
        return $this->belongsToMany(Comic::class);
    }

    /**
     * Find by exact name or create it with its auto-detected category.
     * Uncategorized legacy tags get backfilled on first touch.
     */
    public static function findOrCreateCategorized(string $name): self
    {
        return self::findOrCreateResolved($name);
    }

    /**
     * Resolve an imported tag name: "namespace:name" stores only the
     * short name ("male:dog boy" → "dog boy") with the namespace's
     * category (auto-created with random colors when unknown). Later
     * tags sharing the namespace append to that category. Colliding
     * legacy tags ("male:furry" + "female:furry") merge into one row
     * with comics preserved.
     *
     * Existing categories are preserved unless $forceCategory is set
     * (used by the admin recategorize action, which recomputes all).
     */
    public static function findOrCreateResolved(string $full, bool $forceCategory = false): self
    {
        $resolved = app(TagCategorizer::class)->resolveForSaving($full);
        $short = $resolved['name'] !== '' ? $resolved['name'] : trim($full);
        $category = $resolved['category'];

        $tag = self::where('name', $short)->first();
        if ($tag === null) {
            $legacy = trim($full) !== $short
                ? self::where('name', trim($full))->first()
                : null;

            if ($legacy !== null) {
                $legacy->name = $short;
                $legacy->category_id = $category?->id ?? $legacy->category_id;
                $legacy->save();

                return $legacy->fresh();
            }

            return self::create([
                'name' => $short,
                'category_id' => $category?->id,
            ]);
        }

        if ($category !== null && ($tag->category_id === null || $forceCategory)) {
            $tag->category_id = $category->id;
            $tag->save();
        }

        // A differently-namespaced legacy twin merges into the survivor.
        $twin = trim($full) !== $short
            ? self::where('name', trim($full))->first()
            : null;
        if ($twin !== null && $twin->id !== $tag->id) {
            self::mergeInto($twin, $tag);
        }

        return $tag->fresh();
    }

    /**
     * Fold one tag into another: comics keep a single pivot each.
     */
    public static function mergeInto(self $from, self $to): void
    {
        $existing = DB::table('comic_tag')
            ->where('tag_id', $to->id)
            ->pluck('comic_id')
            ->all();

        foreach (DB::table('comic_tag')->where('tag_id', $from->id)->orderBy('id')->get() as $row) {
            if (in_array($row->comic_id, $existing, true)) {
                DB::table('comic_tag')->where('id', $row->id)->delete();
            } else {
                DB::table('comic_tag')->where('id', $row->id)->update(['tag_id' => $to->id]);
                $existing[] = $row->comic_id;
            }
        }

        $from->delete();
    }
}
