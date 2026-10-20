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

    /** Days before release that members get to read a scheduled comic. */
    public const EARLY_ACCESS_DAYS = 7;

    /** Days after release that a comic still carries the "Novo" ribbon. */
    public const NEW_WINDOW_DAYS = 7;

    // The fields that are mass assignable
    protected $fillable = [
        'title',
        'description',
        'author',
        'image_path',
        'slug',
        'published_at',
        'is_exclusive',
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
            'is_exclusive' => 'boolean',
        ];
    }

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
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
     * @return array<string, int>
     */
    public static function scheduledDateCounts(?int $excludingComicId = null): array
    {
        if (! Schema::hasColumn('comics', 'published_at')) {
            return [];
        }

        return self::scheduled()
            ->when($excludingComicId !== null, fn (Builder $query) => $query->where('id', '!=', $excludingComicId))
            ->selectRaw('DATE(published_at) as schedule_date, COUNT(*) as comic_count')
            ->groupByRaw('DATE(published_at)')
            ->orderByRaw('DATE(published_at)')
            ->pluck('comic_count', 'schedule_date')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * What $user may see in lists.
     *
     * Everything released, plus - for signed-in members only - the last
     * week before a comic's scheduled date (early access). Owners keep
     * their own whole queue and admins see every scheduled comic.
     * Exclusive comics stay listed for guests on purpose: they become
     * locked teaser cards (see isLockedFor) instead of disappearing.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $query;
        }

        if ($user !== null && (int) $user->admin_level >= 1) {
            return $query;
        }

        $earlyAccessUntil = $user !== null ? now()->addDays(self::EARLY_ACCESS_DAYS) : null;

        return $query->where(function (Builder $inner) use ($user, $earlyAccessUntil) {
            $inner->whereNull('comics.published_at')
                ->orWhere('comics.published_at', '<=', now());
            if ($earlyAccessUntil !== null) {
                $inner->orWhere('comics.published_at', '<=', $earlyAccessUntil);
            }
            if ($user !== null) {
                $inner->orWhere('comics.user_id', $user->id);
            }
        });
    }

    /**
     * Public timeline order: when the comic went live (`published_at` when
     * set, upload time otherwise), so a comic scheduled weeks ago lands on
     * top the day it releases instead of sitting in upload order. The id
     * tie-break keeps pagination stable when two releases collide.
     */
    public function scopeOrderByReleaseDate(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        // Hosts without the scheduling column keep upload order.
        if (! Schema::hasColumn('comics', 'published_at')) {
            return $query->orderBy('comics.created_at', $direction)->orderBy('comics.id', $direction);
        }

        return $query->orderByRaw("COALESCE(comics.published_at, comics.created_at) {$direction}")
            ->orderBy('comics.id', $direction);
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
     * Members-only comic: guests never open it, but the lists still show
     * a locked teaser card.
     */
    public function isExclusive(): bool
    {
        return (bool) ($this->is_exclusive ?? false);
    }

    /**
     * Scheduled for release within the members-only early access window
     * (the last week before the comic goes public).
     */
    public function isInEarlyAccessWindow(): bool
    {
        return $this->isScheduled()
            && $this->published_at->lessThanOrEqualTo(now()->addDays(self::EARLY_ACCESS_DAYS));
    }

    /**
     * Released recently enough to carry the "Novo" ribbon.
     */
    public function isNew(): bool
    {
        $releasedAt = $this->published_at ?? $this->created_at;

        if ($releasedAt === null || $releasedAt->isFuture()) {
            return false;
        }

        return $releasedAt->greaterThanOrEqualTo(now()->subDays(self::NEW_WINDOW_DAYS));
    }

    /**
     * Shown to this viewer as a locked card: exclusive content and no
     * signed-in account to unlock it.
     */
    public function isLockedFor(?User $user): bool
    {
        return $this->isExclusive() && $user === null;
    }

    /**
     * May $user read the pages? Owners and admins open anything (their
     * own queue included), members open exclusives and comics inside the
     * early access window, and guests only ever see released comics.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user !== null) {
            if ((int) $this->user_id === (int) $user->id || (int) $user->admin_level >= 1) {
                return true;
            }
        }

        if ($this->isLockedFor($user)) {
            return false;
        }

        return $this->isPublished() || ($user !== null && $this->isInEarlyAccessWindow());
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
     * Commercial listings that reference this comic (marketplace).
     */
    public function marketplaceListings(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceListing::class, 'marketplace_listing_comics')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * The single active, approved listing shown as "À venda" on the
     * comic page, if any.
     */
    public function activeMarketplaceListing(): ?MarketplaceListing
    {
        if (! Schema::hasTable('marketplace_listings')) {
            return null;
        }

        return $this->marketplaceListings()->visible()->first();
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
