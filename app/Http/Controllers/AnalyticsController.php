<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function store(Request $request)
    {
        // Identity is derived server-side: client-supplied user_id /
        // ip_address are ignored so callers can't forge them.
        $validated = $request->validate([
            'url' => 'required|string|max:2000',
            'user_agent' => 'required|string|max:1000',
            'event_type' => 'required|string|max:50',
            'device_type' => 'required|string|max:50',
            'referral_source' => 'nullable|string|max:2000',
            'campaign' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:0|max:86400',
            'browser' => 'nullable|string|max:100',
            'os' => 'nullable|string|max:100',
        ]);

        // Create the analytics entry
        Analytics::create([
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'url' => $validated['url'],
            'user_agent' => $validated['user_agent'],
            'event_type' => $validated['event_type'],
            'device_type' => $validated['device_type'], // Save device type
            'referral_source' => $validated['referral_source'] ?? null, // Save referral source
            'campaign' => $validated['campaign'] ?? null, // Save campaign
            'duration' => $validated['duration'] ?? null, // Save duration
            'browser' => $validated['browser'] ?? null, // Save browser
            'os' => $validated['os'] ?? null, // Save OS
        ]);

        return response()->json(['message' => 'Analytics data stored successfully.'], 201);
    }

    public function referralAnalytics()
    {
        // Capped: the full table would blow memory on large installs.
        $analyticsData = Analytics::select('referral_source', 'url')
            ->latest()
            ->limit(5000)
            ->get();

        // Root node for the tree structure
        $treeData = ['name' => 'Root', 'children' => []];

        // Helper function to find or create a node
        function &findOrCreateNode(&$children, $name)
        {
            foreach ($children as &$child) {
                if ($child['name'] === $name) {
                    return $child;
                }
            }
            // Node not found, create a new one
            $children[] = ['name' => $name, 'children' => []];

            return $children[array_key_last($children)];
        }

        foreach ($analyticsData as $entry) {
            $referral = $entry->referral_source ?: '/';
            $url = $entry->url;

            // Find or create the referral node
            $referralNode = &findOrCreateNode($treeData['children'], $referral);

            // Add the URL as a child node of the referral node (if it doesn't already exist)
            findOrCreateNode($referralNode['children'], $url);
        }

        // Output the treeData structure for debugging purposes
        // dd($treeData);

        return view('admin.referral', [
            'treeData' => json_encode($treeData),
        ]);
    }
}
