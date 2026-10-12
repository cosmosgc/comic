<?php

namespace App\Http\Middleware;

use App\Models\Analytics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
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
