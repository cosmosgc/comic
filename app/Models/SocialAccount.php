<?php

namespace App\Models;

use App\Support\OAuthProviders;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider identity linked to a local user. Public profile fields are
 * copied on each login so the account list stays useful even if the
 * provider later changes the display name.
 */
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'nickname',
        'name',
        'email',
        'avatar_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerLabel(): string
    {
        return OAuthProviders::label($this->provider);
    }

    public function displayName(): string
    {
        return (string) ($this->nickname ?: $this->name ?: $this->email ?: $this->provider_user_id);
    }
}
