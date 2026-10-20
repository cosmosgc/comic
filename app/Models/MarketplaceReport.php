<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report against a listing (message reports are stored with a null
 * message reference until the admin moderation UI ships).
 */
class MarketplaceReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'marketplace_listing_id',
        'marketplace_message_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    public static function reasonOptions(): array
    {
        return [
            'prohibited' => 'Item proibido',
            'spam' => 'Spam',
            'scam' => 'Golpe / fraude',
            'stolen' => 'Item roubado',
            'counterfeit' => 'Falsificação',
            'other' => 'Outro motivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
