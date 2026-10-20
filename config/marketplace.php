<?php

/**
 * Marketplace tuning. Every key can be overridden at runtime from the
 * marketplace_settings table (same pattern as forum/gamification).
 *
 * There is no currency exchange, checkout or escrow here: `price` is
 * always a reference/asking value shown to visitors.
 */
return [

    // Master switch — when false the marketplace 404s for everyone.
    'enabled' => (bool) env('MARKETPLACE_ENABLED', true),

    // New listings wait for an admin before becoming public.
    'require_approval' => false,

    // Display label only; no conversion is ever applied.
    'default_currency' => 'BRL',
    'show_prices' => true,

    // Lifecycle.
    'default_expiry_days' => 60,
    'bump_cooldown_hours' => 24,
    'max_active_listings_per_user' => 20,

    // Uploads.
    'max_images' => 6,
    'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

    // Contact happens off-platform: these are the modes a seller may pick.
    'contact_modes' => ['in_app', 'in_app_and_email', 'email_only'],

    // Words rejected in titles/descriptions/tags.
    'prohibited_words' => [],

    // Fixed safety notice rendered on every listing page.
    'safety_note' => 'Combine tudo pelo chat e nunca envie dinheiro adiantado. Encontros presenciais: em local público e movimentado.',
];
