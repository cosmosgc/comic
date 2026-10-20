<?php

namespace App\Policies;

use App\Models\MarketplaceListing;
use App\Models\User;

class MarketplaceListingPolicy
{
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function view(User $user, MarketplaceListing $listing): bool
    {
        return $listing->isPubliclyVisible() || (int) $listing->user_id === (int) $user->id;
    }

    public function update(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->id;
    }

    public function delete(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->id;
    }

    public function changeStatus(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->id;
    }

    public function contact(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id !== (int) $user->id && $listing->isPubliclyVisible();
    }

    public function watch(User $user, MarketplaceListing $listing): bool
    {
        return $listing->isPubliclyVisible();
    }

    public function report(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id !== (int) $user->id;
    }

    public function moderate(User $user): bool
    {
        return false; // before() grants admins.
    }
}
