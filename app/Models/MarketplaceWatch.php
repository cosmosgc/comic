<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's saved listing. The table is the source of truth; the
 * listing's watch_count is a cached display counter.
 */
class MarketplaceWatch extends Model
{
    protected $fillable = ['user_id', 'marketplace_listing_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }
}
