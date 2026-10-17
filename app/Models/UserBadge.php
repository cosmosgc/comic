<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    protected $fillable = ['user_id', 'badge', 'awarded_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Definition from config/gamification.php (label, icon, …), or a
     * graceful fallback for badges that were later renamed/removed.
     *
     * @return array{label: string, description: string, icon: string, metric: string, threshold: int}
     */
    public function definition(): array
    {
        return config("gamification.badges.{$this->badge}", [
            'label' => ucfirst(str_replace('_', ' ', (string) $this->badge)),
            'description' => '',
            'icon' => '🏅',
            'metric' => '',
            'threshold' => 0,
        ]);
    }
}
