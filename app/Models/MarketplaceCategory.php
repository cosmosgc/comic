<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A marketplace section (parent_id = null) or category (child).
 */
class MarketplaceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_visible',
        'listing_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'listing_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function url(): string
    {
        return route('marketplace.category', ['categorySlug' => $this->slug]);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class);
    }

    public function isSection(): bool
    {
        return $this->parent_id === null;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeSections(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function refreshCounters(): void
    {
        $this->listing_count = $this->listings()->visible()->count();
        $this->save();
    }
}
