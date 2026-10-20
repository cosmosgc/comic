<?php

namespace App\Support;

use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace-wide helpers. Kept out of controllers/policies so the
 * rules (feature switch, limits, prohibited words) live in one place
 * and stay safe on hosts with lagging migrations.
 */
class Marketplace
{
    public static function enabled(): bool
    {
        return (bool) config('marketplace.enabled', true);
    }

    public static function needsApproval(): bool
    {
        return (bool) config('marketplace.require_approval', false);
    }

    public static function maxImages(): int
    {
        return max(1, (int) config('marketplace.max_images', 6));
    }

    public static function maxActiveListingsPerUser(): int
    {
        return max(1, (int) config('marketplace.max_active_listings_per_user', 20));
    }

    public static function defaultCurrency(): string
    {
        return strtoupper(substr((string) config('marketplace.default_currency', 'BRL'), 0, 3)) ?: 'BRL';
    }

    public static function defaultExpiryDays(): int
    {
        return max(1, (int) config('marketplace.default_expiry_days', 60));
    }

    public static function bumpCooldownHours(): int
    {
        return max(1, (int) config('marketplace.bump_cooldown_hours', 24));
    }

    public static function showPrices(): bool
    {
        return (bool) config('marketplace.show_prices', true);
    }

    /**
     * How many listings count against the per-user cap. Drafts are
     * included so a member cannot pile up unpublished listings.
     */
    public static function activeListingsCount(User $user): int
    {
        if (! Schema::hasTable('marketplace_listings')) {
            return 0;
        }

        return $user->marketplaceListings()
            ->whereIn('status', ['draft', 'active', 'reserved'])
            ->count();
    }

    /**
     * Reject prohibited words in user-generated text. Returns the
     * offending word or null.
     */
    public static function prohibitedWord(?string $text): ?string
    {
        $words = config('marketplace.prohibited_words', []);
        if (! is_array($words) || $words === []) {
            return null;
        }

        $haystack = mb_strtolower((string) $text);
        foreach ($words as $word) {
            $word = trim(mb_strtolower((string) $word));
            if ($word !== '' && str_contains($haystack, $word)) {
                return $word;
            }
        }

        return null;
    }

    /**
     * Currency display label. BRL gets the familiar symbol; anything
     * else is shown as the three-letter code.
     */
    public static function currencyLabel(?string $currency): string
    {
        $currency = strtoupper((string) ($currency ?: self::defaultCurrency()));

        return $currency === 'BRL' ? 'R$' : $currency;
    }

    /**
     * Overlay database overrides onto config. Called from the service
     * provider and available to tests explicitly.
     */
    public static function applySettings(): void
    {
        if (! Schema::hasTable('marketplace_settings')) {
            return;
        }

        foreach (MarketplaceSetting::all() as $row) {
            if (! array_key_exists($row->key, MarketplaceSetting::definitions())) {
                continue;
            }
            config(['marketplace.'.$row->key => MarketplaceSetting::castValue($row->key, $row->value)]);
        }
    }
}
