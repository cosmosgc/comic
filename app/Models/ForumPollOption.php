<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One selectable answer in a forum poll.
 */
class ForumPollOption extends Model
{
    protected $fillable = ['forum_poll_id', 'label', 'position', 'votes_count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['votes_count' => 'integer'];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(ForumPoll::class, 'forum_poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ForumPollVote::class);
    }
}
