<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Comic extends Model
{
    use HasFactory;

    // Specify the table if necessary (Laravel assumes the plural form of the class name)
    protected $table = 'comics';

    // The fields that are mass assignable
    protected $fillable = [
        'title',
        'description',
        'author',
        'image_path',
        'slug',
        'published_at',
        'user_id',
        'view_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title); // Create the base slug
        if ($slug === '') {
            // Titles made only of symbols/emoji would otherwise yield an
            // unreachable empty slug.
            $slug = 'comic';
        }
        $originalSlug = $slug; // Keep the original slug for comparison
        $count = 1;

        // Check if the slug already exists and append a number if needed
        while (Comic::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Publicly visible comics: never scheduled, or the schedule already
     * passed. Hosts without the migration see everything (no column).
     */
    public function scopePublished(Builder $query): Builder
    {
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $query;
        }

        return $query->where(function (Builder $inner) {
            $inner->whereNull('comics.published_at')
                ->orWhere('comics.published_at', '<=', now());
        });
    }

    /**
     * Comics waiting for their scheduled date (future published_at).
     */
    public function scopeScheduled(Builder $query): Builder
    {
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('comics.published_at', '>', now());
    }

    /**
     * What $user may see in lists: everything published, plus their own
     * scheduled comics (admins see all scheduled). Guests get published.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $query;
        }

        if ($user !== null && (int) $user->admin_level >= 1) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->whereNull('comics.published_at')
                ->orWhere('comics.published_at', '<=', now());
            if ($user !== null) {
                $inner->orWhere('comics.user_id', $user->id);
            }
        });
    }

    /**
     * Default schedule for the next comic: one day after the latest
     * scheduled one, or tomorrow when the queue is empty. Drives the
     * prefilled date in the admin import (and anywhere else that
     * schedules ahead).
     */
    public static function defaultScheduledDate(): CarbonInterface
    {
        $fallback = now()->addDay();
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $fallback;
        }

        $max = self::where('published_at', '>', now())->max('published_at');

        return $max ? Carbon::parse($max)->addDay() : $fallback;
    }

    /**
     * Still waiting for its scheduled date.
     */
    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * Publicly available right now.
     */
    public function isPublished(): bool
    {
        return ! $this->isScheduled();
    }

    /**
     * Owner and admins may preview scheduled comics; everyone else
     * only sees published ones.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->isPublished()) {
            return true;
        }

        return $user !== null
            && ((int) $this->user_id === (int) $user->id || (int) $user->admin_level >= 1);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function pageCount(): int
    {
        return $this->pages()->count();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * E-Hentai import tracking (one record per comic, cascade-deleted
     * with it). Null for manually uploaded comics.
     */
    public function ehImport(): HasOne
    {
        return $this->hasOne(EhImport::class);
    }

    /**
     * Outgoing server-to-server transfer state (local side only).
     */
    public function transfer(): HasOne
    {
        return $this->hasOne(ComicTransfer::class);
    }

    /**
     * Tags grouped by category label, ordered by the category's
     * sort order (artist → copyright → character → …).
     * Hidden categories are excluded (their tags stay stored and
     * searchable, they just don't render).
     * Powers the category-grouped tag list on the reader page.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Tag>>
     */
    public function tagsGroupedByCategory(): \Illuminate\Support\Collection
    {
        return $this->tags
            ->filter(fn (Tag $tag) => ($tag->displayCategory?->is_visible ?? true))
            ->groupBy(fn (Tag $tag) => $tag->displayCategory?->id ?? 0)
            ->sortBy(fn ($tags) => $tags->first()->displayCategory?->sort_order ?? 9999)
            ->mapWithKeys(fn ($tags) => [
                ($tags->first()->displayCategory?->label ?? 'General') => $tags->values(),
            ]);
    }

    public function collections(): BelongsToMany
    {
        // NOTE: no default order here on purpose (see Collection::comics):
        // orderBy('pivot_order') leaks into exists()/first()/count()
        // subqueries where the alias isn't selected (MySQL 1054).
        return $this->belongsToMany(Collection::class)
            ->withPivot('order'); // Include the 'order' column in the pivot table
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comic_user_likes')->withTimestamps();
    }

    public function setCoverFromPage(Page $page): void
    {
        $this->image_path = $page->image_path;
        $this->save();
    }
}
