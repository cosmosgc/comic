<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class CollectionPolicy
{
    /**
     * Admins may do anything.
     */
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    /**
     * Private collections are visible to the owner (and admins) only. The
     * nullable user lets guests reach this method (Laravel skips policy
     * methods typed against a non-null User for guests), so a public
     * collection still renders for logged-out visitors.
     */
    public function view(?User $user, Collection $collection): bool
    {
        if (! $this->ownsColumns()) {
            return true; // Legacy global collections predate ownership.
        }

        if ($collection->is_public) {
            return true;
        }

        return $user !== null && $collection->isOwnedBy($user);
    }

    /**
     * Write access: owner or admin. Legacy ownerless rows stay editable
     * (any signed-in user) so old hosts keep working until they migrate.
     */
    public function update(?User $user, Collection $collection): bool
    {
        if (! $this->ownsColumns() || $collection->user_id === null) {
            return $user !== null;
        }

        return $user !== null && $collection->isOwnedBy($user);
    }

    /**
     * Ownership columns exist only after the add_owner migration runs.
     * Hosts without console access may lag behind, so every ownership
     * feature degrades to the legacy global behavior instead of fataling.
     */
    protected function ownsColumns(): bool
    {
        return Schema::hasColumn('collections', 'user_id')
            && Schema::hasColumn('collections', 'is_public');
    }
}
