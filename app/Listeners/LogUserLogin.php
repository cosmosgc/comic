<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\Analytics;
use Illuminate\Http\Request;

class LogUserLogin
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event)
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
