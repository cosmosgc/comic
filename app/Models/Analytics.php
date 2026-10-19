<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'url',
        'event_type',
        'user_id',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'duration',           // Duration in seconds
        'referral_source',
        'campaign',
    ];

    // Define relationship with User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
