<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A discussion thread. The opening post lives in forum_posts.
 */
class ForumThread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'forum_category_id',
        'user_id',
        'title',
        'slug',
        'type',
        'is_pinned',
        'is_locked',
        'is_approved',
        'last_post_at',
        'last_post_user_id',
        'solved_post_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_approved' => 'boolean',
            'last_post_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastPoster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->orderBy('position');
    }

    public function openingPost(): HasOne
    {
        return $this->hasOne(ForumPost::class)->orderBy('position');
    }

    public function solvedPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'solved_post_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ForumSubscription::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ForumThreadRead::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ForumTag::class, 'forum_tag_thread')->withTimestamps();
    }

    public function isQuestion(): bool
    {
        return $this->type === 'question';
    }

    public function isPoll(): bool
    {
        return $this->type === 'poll';
    }

    public function poll(): HasOne
    {
        return $this->hasOne(ForumPoll::class);
    }

    public function isSolved(): bool
    {
        return $this->solved_post_id !== null;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('last_post_at');
    }

    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->where('reply_count', 0);
    }

    /**
     * Whether the thread has posts newer than the user's last read.
     */
    public function isUnreadFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $read = ForumThreadRead::query()
            ->where('user_id', $user->id)
            ->where('forum_thread_id', $this->id)
            ->first();

        if ($read === null || $read->last_read_post_id === null) {
            return $this->reply_count > 0 || $this->posts()->where('position', '>', 1)->exists();
        }

        return $this->posts()
            ->where('id', '>', (int) $read->last_read_post_id)
            ->exists();
    }

    /**
     * Build a slug unique within the category.
     */
    public static function uniqueSlug(int $categoryId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'topico';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()
            ->where('forum_category_id', $categoryId)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    public function refreshCounters(): void
    {
        $this->reply_count = $this->posts()->where('position', '>', 1)->count();
        $this->reaction_count = ForumPostReaction::query()
            ->whereIn('forum_post_id', $this->posts()->select('id'))
            ->count();

        $last = $this->posts()->latest('id')->first();
        $this->last_post_at = $last?->created_at ?? $this->created_at;
        $this->last_post_user_id = $last?->user_id;
        $this->save();
    }
}
