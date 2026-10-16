<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server-to-server comic transfer (local → remote)
    |--------------------------------------------------------------------------
    |
    | This installation plays both sides with the same keys:
    | - TRANSFER_API_TOKEN authorizes incoming transfers TO this site.
    | - TRANSFER_REMOTE_URL + TRANSFER_REMOTE_TOKEN point AT the other
    |   site when sending comics FROM here (Admin → Comics → Enviar).
    |
    */

    // Incoming transfers must present this as a Bearer token.
    'api_token' => env('TRANSFER_API_TOKEN', ''),

    // Outgoing transfers: base URL of the other site, e.g.
    // http://177.190.68.79:3000/comic/public
    'remote_url' => env('TRANSFER_REMOTE_URL', ''),

    // Token configured as TRANSFER_API_TOKEN on the other site.
    'remote_token' => env('TRANSFER_REMOTE_TOKEN', ''),

    // Seconds per file upload request.
    'timeout' => (int) env('TRANSFER_TIMEOUT', 60),

    // Pages pushed per progress poll.
    'chunk' => (int) env('TRANSFER_CHUNK', 2),

];
