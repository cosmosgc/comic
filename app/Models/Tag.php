<?php

namespace App\Models;

use App\Services\TagCategorizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        $name = trim($name);

        $tag = self::where('name', $name)->first();
        if ($tag !== null) {
            if ($tag->category_id === null) {
                $tag->category_id = app(TagCategorizer::class)->forName($name)?->id;
                $tag->save();
            }

            return $tag;
        }

        return self::create([
            'name' => $name,
            'category_id' => app(TagCategorizer::class)->forName($name)?->id,
        ]);
    }
}
