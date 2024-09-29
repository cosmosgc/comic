<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Analytics;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function store(Request $request)
    {
        // Validate the request data
        $validated = $request->validate([
            'user_id'=> 'nullable|string',
            'url' => 'required|string',
            'ip_address' => 'required|string',
            'user_agent' => 'required|string',
            'event_type' => 'required|string',
            'device_type' => 'required|string', // New field for device type
            'referral_source' => 'nullable|string', // New field for referral source
            'campaign' => 'nullable|string', // New field for campaign
            'duration' => 'nullable|integer', // Duration in seconds
            'browser' => 'nullable|string', // New field for browser
            'os' => 'nullable|string', // New field for OS
        ]);

        // Create the analytics entry
        Analytics::create([
            'user_id' => $validated['user_id'],
            'ip_address' => $validated['ip_address'],
            'url' => $validated['url'],
            'user_agent' => $validated['user_agent'],
            'event_type' => $validated['event_type'],
            'device_type' => $validated['device_type'], // Save device type
            'referral_source' => $validated['referral_source'], // Save referral source
            'campaign' => $validated['campaign'], // Save campaign
            'duration' => $validated['duration'], // Save duration
            'browser' => $validated['browser'], // Save browser
            'os' => $validated['os'], // Save OS
        ]);

        return response()->json(['message' => 'Analytics data stored successfully.'], 201);
    }
    public function referralAnalytics()
    {
        // Fetch analytics data grouped by referral source and URL
        $analyticsData = Analytics::select('referral_source', 'url')
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
        //dd($treeData);

        return view('admin.referral', [
            'treeData' => json_encode($treeData),
        ]);
    }






}
