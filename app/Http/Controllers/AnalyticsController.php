<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Analytics;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function store(Request $request)
    {
        // Validate the request data
        $validated = $request->validate([
            'url' => 'required|string',
            'ip_address' => 'required|string',
            'user_agent' => 'required|string',
            'event_type' => 'required|string',
        ]);

        // Create the analytics entry
        Analytics::create([
            'user_id' => Auth::id(),  // Optional, tracks logged in users
            'ip_address' => $validated['ip_address'],
            'url' => $validated['url'],
            'user_agent' => $validated['user_agent'],
            'event_type' => $validated['event_type'],
        ]);

        return response()->json(['message' => 'Analytics data stored successfully.'], 201);
    }
}
