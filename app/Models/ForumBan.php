<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A forum ban: forum-wide (forum_category_id null) or per-board.
 * banned_until null = permanent.
 */
class ForumBan extends Model
{
    protected $fillable = ['user_id', 'forum_category_id', 'banned_by', 'reason', 'banned_until'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['banned_until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function isPermanent(): bool
    {
        return $this->banned_until === null;
    }

    public function isActive(): bool
    {
        return $this->banned_until === null || $this->banned_until->isFuture();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('banned_until')
            ->orWhere('banned_until', '>', now()));
    }

    /**
     * Whether this ban blocks the user in the given category (null =
     * forum-wide check).
     */
    public function coversCategory(?int $categoryId): bool
    {
        return $this->forum_category_id === null
            || ($categoryId !== null && (int) $this->forum_category_id === $categoryId);
    }
}
