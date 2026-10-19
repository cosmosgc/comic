<?php

namespace App\Policies;

use App\Models\Comic;
use App\Models\User;

/**
 * Owner-or-admin authorization for a comic and its pages. Every comic
 * mutation (edit/update, reorder pages, set the cover, add or delete a
 * page) uses the `update` ability: they all mean "may modify this comic".
 */
class ComicPolicy
{
    /**
     * Admins may do anything.
     */
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    /**
     * Legacy comics with no owner (user_id null) stay admin-only, since
     * (int) null never matches a real user id.
     */
    public function update(User $user, Comic $comic): bool
    {
        return (int) $comic->user_id === (int) $user->id;
    }
}
