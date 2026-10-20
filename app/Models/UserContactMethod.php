<?php

namespace App\Models;

use App\Support\ContactMethod;
use Database\Factories\UserContactMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One saved contact channel on a user profile. A method can be hidden from
 * the profile (`is_public = false`) while still being selected for a listing.
 */
class UserContactMethod extends Model
{
    /** @use HasFactory<UserContactMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'value',
        'is_public',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marketplaceListings(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceListing::class,
            'marketplace_listing_contact_method',
            'user_contact_method_id',
            'marketplace_listing_id'
        )->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public function typeLabel(): string
    {
        return ContactMethod::label($this->type);
    }

    public function displayValue(): string
    {
        return ContactMethod::display($this->type, $this->value);
    }

    public function url(?string $subject = null): ?string
    {
        return ContactMethod::url($this->type, $this->value, $subject);
    }
}
