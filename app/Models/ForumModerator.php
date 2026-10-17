<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-board (or forum-wide when forum_category_id is null) moderator.
 */
class ForumModerator extends Model
{
    protected $fillable = ['user_id', 'forum_category_id', 'granted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isForumWide(): bool
    {
        return $this->forum_category_id === null;
    }
}
