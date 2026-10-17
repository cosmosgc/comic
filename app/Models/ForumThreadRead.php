<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user read marker for a thread ("new posts" dots).
 */
class ForumThreadRead extends Model
{
    protected $fillable = ['user_id', 'forum_thread_id', 'last_read_post_id', 'last_read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }
}
