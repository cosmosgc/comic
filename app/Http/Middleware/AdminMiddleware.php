<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || Auth::user()->admin_level < 1) {
            return redirect('/'); // Redirect to root if not admin
        }

        return $next($request);
    }
}

