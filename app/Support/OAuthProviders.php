<?php

namespace App\Support;

/**
 * Provider registry for social login. Keeps the "is this provider usable?"
 * checks in one place so the routes, Blade buttons and controller agree.
 */
class OAuthProviders
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $providers = config('oauth.providers', []);

        return is_array($providers) ? $providers : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $name): ?array
    {
        $provider = static::all()[$name] ?? null;

        return is_array($provider) ? $provider : null;
    }

    public static function isConfigured(string $name): bool
    {
        $provider = static::get($name);

        if ($provider === null) {
            return false;
        }

        return filled($provider['client_id'] ?? null)
            && filled($provider['client_secret'] ?? null);
    }

    /**
     * Only providers with credentials are shown to visitors.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function configured(): array
    {
        return array_filter(
            static::all(),
            fn (array $provider, string $name) => static::isConfigured($name),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function label(string $name): string
    {
        return (string) (static::get($name)['label'] ?? ucfirst($name));
    }

    public static function callbackUrl(string $name): string
    {
        $configured = static::get($name)['redirect'] ?? null;

        return filled($configured)
            ? (string) $configured
            : route('oauth.callback', ['provider' => $name]);
    }
}
