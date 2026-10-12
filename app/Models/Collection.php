<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'is_public',
        'is_favorites',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_favorites' => 'boolean',
        ];
    }

    public function comics(): BelongsToMany
    {
        // NOTE: no default order here on purpose. An orderBy('pivot_order')
        // on the relation leaks into exists()/first()/count() subqueries,
        // where the pivot alias is not selected — MySQL throws 1054
        // (SQLite silently tolerates it, so tests can't catch it).
        // Order explicitly at display sites via orderedComics().
        return $this->belongsToMany(Comic::class)
            ->withPivot('order'); // Include the 'order' column in the pivot table
    }

    /**
     * Comics in manual sort order (for display).
     */
    public function orderedComics(): BelongsToMany
    {
        return $this->comics()->orderBy('collection_comic.order');
    }

    protected ?Comic $coverCache = null;

    protected bool $coverLoaded = false;

    /**
     * First comic's cover for cards and headers (memoized per instance).
     */
    public function coverComic(): ?Comic
    {
        if (! $this->coverLoaded) {
            $this->coverCache = $this->orderedComics()->first();
            $this->coverLoaded = true;
        }

        return $this->coverCache;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * The owner's built-in Favorites collection (single per user,
     * enforced here rather than by a partial unique index).
     */
    public static function favoritesFor(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id, 'is_favorites' => true],
            ['name' => 'Favorites', 'description' => null, 'is_public' => false]
        );
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }
}
