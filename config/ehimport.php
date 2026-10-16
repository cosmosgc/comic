<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Full-comic import from E-Hentai (admin panel)
    |--------------------------------------------------------------------------
    |
    | The admin import scrapes gallery pages for the image-page URLs and
    | then downloads each full-size image server-side, a few pages per
    | request (the progress view polls until done). Delays between
    | requests keep E-Hentai's rate limiter happy — do not set the
    | delay to zero outside of tests.
    |
    */

    // GET timeout for gallery / image-page HTML (seconds).
    'html_timeout' => (int) env('EHIMPORT_HTML_TIMEOUT', 15),

    // GET timeout for each full-size image (seconds).
    'image_timeout' => (int) env('EHIMPORT_IMAGE_TIMEOUT', 45),

    // Polite pause between outbound requests (milliseconds).
    'delay_ms' => (int) env('EHIMPORT_DELAY_MS', 1200),

    // Random extra pause added to every delay (milliseconds) so the
    // request rhythm doesn't look robotic to rate limiters.
    'delay_jitter_ms' => (int) env('EHIMPORT_DELAY_JITTER_MS', 800),

    // Extra pause BEFORE each full-size image download (milliseconds):
    // images are the bandwidth-heavy calls that trigger 509s and
    // firewall attention.
    'image_delay_ms' => (int) env('EHIMPORT_IMAGE_DELAY_MS', 3000),

    // Hard ceiling: image downloads per rolling minute, shared by all
    // imports on this server. Excess polls get HTTP 429 + Retry-After
    // and the progress view backs off automatically.
    'max_per_minute' => (int) env('EHIMPORT_MAX_PER_MINUTE', 20),

    // Image pages downloaded per progress-poll request.
    'chunk' => (int) env('EHIMPORT_CHUNK', 3),

    // Safety caps: gallery index pages scanned, total pages imported.
    'max_index_pages' => (int) env('EHIMPORT_MAX_INDEX_PAGES', 25),

    'max_pages' => (int) env('EHIMPORT_MAX_PAGES', 600),

    // Refuse single images larger than this (megabytes).
    'max_image_mb' => (int) env('EHIMPORT_MAX_IMAGE_MB', 20),

    // Optional image relay for hosts blocking the hath network's random
    // ports (cURL error 7). Must end with the url parameter, e.g.
    // https://images.weserv.nl/?url= — downloads fall back to it only
    // when the direct fetch fails.
    'image_proxy' => env('EHIMPORT_IMAGE_PROXY', ''),

    // Download attempts per page before it is reported as failed.
    'max_attempts' => (int) env('EHIMPORT_MAX_ATTEMPTS', 3),

    // How long a fetched preview stays valid for the edit form.
    'preview_ttl' => (int) env('EHIMPORT_PREVIEW_TTL', 3600),

];
