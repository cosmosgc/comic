<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * A post may be edited by its author or an admin.
 */
class PostPolicy
{
    /**
     * Admins may do anything.
     */
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function update(User $user, Post $post): bool
    {
        return (int) $post->author_id === (int) $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $post->author_id === (int) $user->id;
    }
}
