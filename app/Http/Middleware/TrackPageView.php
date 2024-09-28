<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Analytics;
use Illuminate\Support\Facades\Auth;

class TrackPageView
{
    public function handle(Request $request, Closure $next)
    {
        // Record the page view
        Analytics::create([
            'user_id' => Auth::id(),  // Nullable, tracks logged in users
            'ip_address' => $request->ip(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'event_type' => 'page_view',
        ]);

        return $next($request);
    }
}
