<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'is_public',
        'is_favorites',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_favorites' => 'boolean',
    ];

    public function comics()
    {
        return $this->belongsToMany(Comic::class)
            ->withPivot('order') // Include the 'order' column in the pivot table
            ->orderBy('pivot_order'); // Order comics by the 'order' field in the pivot table
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * The owner's built-in Favorites collection (single per user,
     * enforced here rather than by a partial unique index).
     */
    public static function favoritesFor(User $user): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id, 'is_favorites' => true],
            ['name' => 'Favorites', 'description' => null, 'is_public' => false]
        );
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }
}
