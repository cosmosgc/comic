<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;
use App\Support\Forum;

class ForumThreadPolicy
{
    /**
     * Admins may do anything.
     */
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function view(User $user, ForumThread $thread): bool
    {
        return Forum::enabled() && $thread->is_approved;
    }

    public function update(User $user, ForumThread $thread): bool
    {
        return (int) $thread->user_id === (int) $user->id
            || Forum::isModerator($user, $thread->category);
    }

    public function delete(User $user, ForumThread $thread): bool
    {
        return (int) $thread->user_id === (int) $user->id
            || Forum::isModerator($user, $thread->category);
    }

    /**
     * Lock/pin/move/approve.
     */
    public function moderate(User $user, ForumThread $thread): bool
    {
        return Forum::isModerator($user, $thread->category);
    }

    public function solve(User $user, ForumThread $thread): bool
    {
        return (int) $thread->user_id === (int) $user->id
            || Forum::isModerator($user, $thread->category);
    }
}
