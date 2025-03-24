<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Comic;
use App\Models\Analytics;

use Illuminate\Support\Facades\Hash;


use Illuminate\Http\Request;

class AdminController extends Controller
{
    //

    public function showDashboard()
    {
        $pageViews = Analytics::where('event_type', 'page_view')->count();
        $logins = Analytics::where('event_type', 'login')->count();
        $analytics = Analytics::latest()->paginate(10); // Show recent events

        return view('admin.dashboard', compact('pageViews', 'logins', 'analytics'));
    }

    public function dashboard()
    {
        $totalUsers = User::count();
    $totalComics = Comic::count();

    // Fetch analytics data grouped by day
    $analyticsData = Analytics::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                              ->groupBy('date')
                              ->orderBy('date')
                              ->get();

    // Example: Fetch logins data grouped by day
    $loginsData = Analytics::where('event_type', 'login')
                           ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                           ->groupBy('date')
                           ->orderBy('date')
                           ->get();
    // Pass this data to the view
    return view('admin.dashboard', [
        'totalUsers' => $totalUsers,
        'totalComics' => $totalComics,
        'analyticsData' => $analyticsData,
        'loginsData' => $loginsData
    ]);
    }

    public function analytics()
    {
        $analytics = Analytics::latest()->paginate(1000); // Show recent events

        return view('admin.analytics', compact('analytics'));
    }

    
    public function comics()
    {
        $comics = Comic::all(); // Fetch all users

        return view('admin.comics', compact('comics'));
    }

    public function users()
    {
        $users = User::all(); // Fetch all users

        return view('admin.users', compact('users'));
    }
    public function editUser($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    // Method to update the user details
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        // Validate input
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);


        // Update user data
        $user->name = $request->input('name');
        $user->email = $request->input('email');

        // If password is set, hash it and update
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }


        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    // Method to delete a user
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting the currently authenticated user
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

}
