<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * A comment may be changed by its author or an admin.
 */
class CommentPolicy
{
    /**
     * Admins may do anything.
     */
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function update(User $user, Comment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->id;
    }
}
