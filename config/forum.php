<?php

/**
 * Forum tuning. Every key can be overridden at runtime from the
 * database (Admin → Fórum), mirroring config/gamification.php.
 */
return [

    // Master switch — when false the forum is not publicly readable
    // and all write routes 404.
    'enabled' => (bool) env('FORUM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Posting gates
    |--------------------------------------------------------------------------
    */
    // none | first_post | all — which posts need moderator approval.
    'approval_mode' => 'none',

    // Authors may edit their own post within this window (minutes).
    'edit_grace_minutes' => 30,

    // Minimum account age (hours) before a member may start a thread.
    'min_account_age_hours' => 0,

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    'max_attachments' => 4,
    'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],

    /*
    |--------------------------------------------------------------------------
    | Listing / lifecycle
    |--------------------------------------------------------------------------
    */
    'posts_per_page' => 15,
    'hot_window_days' => 7,
    'auto_lock_days' => 0, // 0 = never auto-lock inactive threads
    'signature_enabled' => false,

    // Words rejected in thread titles / post bodies.
    'prohibited_words' => [],
];
