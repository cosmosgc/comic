<?php

namespace App\Listeners;

use App\Enums\AnalyticsEventType;
use App\Models\Analytics;
use App\Support\ActivityLogger;
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
            'event_type' => AnalyticsEventType::Login->value,
        ]);

        ActivityLogger::log('auth.login', [
            'user' => $event->user,
            'description' => 'Fez login',
            'metadata' => ['email' => $event->user->email ?? null],
            'ip' => $this->request->ip(),
        ]);
    }
}
