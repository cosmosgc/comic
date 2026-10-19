<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's vote for one poll option. A single-choice poll keeps at most
 * one row per user; the controller clears the previous rows on re-vote.
 */
class ForumPollVote extends Model
{
    protected $fillable = ['forum_poll_id', 'forum_poll_option_id', 'user_id'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(ForumPoll::class, 'forum_poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ForumPollOption::class, 'forum_poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
