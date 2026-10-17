<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;
use App\Support\Forum;

class ForumPostPolicy
{
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function view(User $user, ForumPost $post): bool
    {
        return Forum::enabled() && $post->is_approved;
    }

    /**
     * Authors may edit within the grace window; moderators always can.
     */
    public function update(User $user, ForumPost $post): bool
    {
        if (Forum::isModerator($user, $post->thread?->category)) {
            return true;
        }

        return (int) $post->user_id === (int) $user->id && $post->withinEditWindow();
    }

    public function delete(User $user, ForumPost $post): bool
    {
        return (int) $post->user_id === (int) $user->id
            || Forum::isModerator($user, $post->thread?->category);
    }

    public function moderate(User $user, ForumPost $post): bool
    {
        return Forum::isModerator($user, $post->thread?->category);
    }
}
