<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single reaction (like/laugh/…) from a user to a post.
 */
class ForumPostReaction extends Model
{
    protected $fillable = ['forum_post_id', 'user_id', 'reaction'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    public static function kinds(): array
    {
        return [
            'like' => '👍',
            'laugh' => '😂',
            'wow' => '😮',
            'sad' => '😢',
        ];
    }
}
