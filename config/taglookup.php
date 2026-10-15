<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External tag lookup (E-Hentai / ExHentai)
    |--------------------------------------------------------------------------
    |
    | The create/edit comic forms can fetch gallery tags from the E-Hentai
    | API (https://ehwiki.org/wiki/API) and auto-apply the ones that
    | already exist locally. E-Hentai needs NO api key; the optional
    | cookie values below only unlock ExHentai / excluded content.
    |
    */

    // POST endpoint for the `gdata` / `gtoken` methods.
    'api_url' => env('EH_API_URL', 'https://api.e-hentai.org/api.php'),

    // HTML search used when the user types a title instead of a gallery URL.
    'search_url' => env('EH_SEARCH_URL', 'https://e-hentai.org/'),

    // How many search-result galleries to resolve metadata for (1-5).
    'lookup_limit' => (int) env('EH_LOOKUP_LIMIT', 3),

    'timeout' => (int) env('EH_TIMEOUT', 10),

    // Cache seconds for gdata + search responses.
    'cache_ttl' => (int) env('EH_CACHE_TTL', 3600),

    // Optional login cookies for ExHentai / expunged galleries.
    'member_id' => env('EH_IPB_MEMBER_ID'),
    'pass_hash' => env('EH_IPB_PASS_HASH'),
    'igneous' => env('EH_IGNEOUS'),

    // Force all requests through exhentai.org (requires cookies above).
    'use_exhentai' => (bool) env('EXHENTAI_ENABLED', false),

];
