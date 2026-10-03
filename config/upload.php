<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Per-file limit enforced by Laravel validation (friendly 422 error).
    | Total-request limit checked in the browser BEFORE sending, so users
    | get a clear message instead of a raw nginx 413 rejection.
    |
    | Both must stay below the server limits:
    |   nginx:  client_max_body_size 110M;
    |   PHP:    see public/.user.ini (upload_max_filesize / post_max_size)
    |
    */

    'max_file_mb' => (int) env('UPLOAD_MAX_FILE_MB', 10),

    'max_post_mb' => (int) env('UPLOAD_MAX_POST_MB', 100),

    /*
    | Sequential upload tuning (upload page sends one small request per
    | page, so even a 1 MB nginx cap survives). Files bigger than
    | safe_single_kb are recompressed in the browser before sending.
    */

    'safe_single_kb' => (int) env('UPLOAD_SAFE_SINGLE_KB', 900),

    'compress_max_dim' => (int) env('UPLOAD_COMPRESS_MAX_DIM', 2048),

    // Files bigger than this are refused outright (browser can't process them).
    'huge_file_mb' => (int) env('UPLOAD_HUGE_FILE_MB', 50),

];
