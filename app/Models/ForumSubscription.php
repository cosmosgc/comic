<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's subscription to a thread's new posts.
 */
class ForumSubscription extends Model
{
    protected $fillable = ['user_id', 'forum_thread_id', 'notify', 'last_notified_post_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }
}
