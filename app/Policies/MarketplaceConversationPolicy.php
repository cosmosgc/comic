<?php

namespace App\Policies;

use App\Models\MarketplaceConversation;
use App\Models\User;

class MarketplaceConversationPolicy
{
    public function before(User $user): ?bool
    {
        return (int) ($user->admin_level ?? 0) >= 1 ? true : null;
    }

    public function view(User $user, MarketplaceConversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function reply(User $user, MarketplaceConversation $conversation): bool
    {
        return $conversation->hasParticipant($user) && $conversation->status !== 'blocked';
    }
}
