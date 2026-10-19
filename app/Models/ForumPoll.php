<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A poll attached to a forum thread (one per thread).
 */
class ForumPoll extends Model
{
    protected $fillable = ['forum_thread_id', 'question', 'multiple', 'closes_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiple' => 'boolean',
            'closes_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ForumPollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ForumPollVote::class);
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }
}
