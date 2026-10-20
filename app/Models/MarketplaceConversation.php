<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Contact-only thread between a buyer and the seller of one listing.
 */
class MarketplaceConversation extends Model
{
    protected $fillable = [
        'marketplace_listing_id',
        'buyer_id',
        'seller_id',
        'subject',
        'last_message_at',
        'buyer_unread_count',
        'seller_unread_count',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_unread_count' => 'integer',
            'seller_unread_count' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketplaceMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(MarketplaceMessage::class)->latestOfMany();
    }

    public function hasParticipant(?User $user): bool
    {
        return $user !== null
            && ((int) $user->id === (int) $this->buyer_id || (int) $user->id === (int) $this->seller_id);
    }

    public function otherParty(User $user): ?User
    {
        if ((int) $user->id === (int) $this->buyer_id) {
            return $this->seller;
        }

        if ((int) $user->id === (int) $this->seller_id) {
            return $this->buyer;
        }

        return null;
    }

    public function unreadFor(User $user): int
    {
        if ((int) $user->id === (int) $this->buyer_id) {
            return (int) $this->buyer_unread_count;
        }

        if ((int) $user->id === (int) $this->seller_id) {
            return (int) $this->seller_unread_count;
        }

        return 0;
    }

    public function markReadFor(User $user): void
    {
        if ((int) $user->id === (int) $this->buyer_id) {
            $this->buyer_unread_count = 0;
        } elseif ((int) $user->id === (int) $this->seller_id) {
            $this->seller_unread_count = 0;
        }
        $this->save();
    }
}
