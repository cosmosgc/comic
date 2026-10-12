<?php

namespace App\Listeners;

use App\Models\Analytics;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogUserLogin
{
    public function __construct(protected Request $request) {}

    public function handle(Login $event): void
    {
        // Log the login event
        Analytics::create([
            'user_id' => $event->user->id,
            'ip_address' => $this->request->ip(),
            'url' => 'login',  // You can specify a URL or event name
            'user_agent' => $this->request->userAgent(),
            'event_type' => 'login',
        ]);
    }
}
