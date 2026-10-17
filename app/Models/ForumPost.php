<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A thread post. position = 1 is the opening post; everything else is
 * a reply. body stores the raw (markdown) source.
 */
class ForumPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'forum_thread_id',
        'user_id',
        'parent_id',
        'body',
        'body_format',
        'position',
        'is_approved',
        'edited_at',
        'edit_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ForumPostReaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ForumReport::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ForumAttachment::class, 'attachable');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function isOpening(): bool
    {
        return (int) $this->position === 1;
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * Whether the author may still edit within the grace window (or is
     * a moderator, handled by the policy).
     */
    public function withinEditWindow(): bool
    {
        $grace = (int) config('forum.edit_grace_minutes', 30);
        if ($grace <= 0) {
            return false;
        }

        return $this->created_at !== null
            && $this->created_at->copy()->addMinutes($grace)->isFuture();
    }
}
