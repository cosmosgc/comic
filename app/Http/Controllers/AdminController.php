<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Comic;
use App\Models\Analytics;

use Illuminate\Support\Facades\Hash;


use Illuminate\Http\Request;
use Carbon\Carbon;

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

    public function dashboard(Request $request)
    {
        $totalUsers = User::count();
        $totalComics = Comic::count();

        // Handle optional date filters from the request
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        // Helper function to apply date range
        $applyDateRange = function ($query) use ($startDate, $endDate) {
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
            return $query;
        };

        // Daily Page Views
        $dailyAnalytics = $applyDateRange(
            Analytics::selectRaw('DATE(created_at) as date, COUNT(*) as count')
        )->groupBy('date')
        ->orderBy('date')
        ->get();

        // Monthly Page Views
        $monthlyAnalytics = $applyDateRange(
            Analytics::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as date, COUNT(*) as count')
        )->groupBy('date')
        ->orderBy('date')
        ->get();

        // Annual Page Views
        $annualAnalytics = $applyDateRange(
            Analytics::selectRaw('YEAR(created_at) as date, COUNT(*) as count')
        )->groupBy('date')
        ->orderBy('date')
        ->get();

        return view('admin.dashboard', [
            'totalUsers' => $totalUsers,
            'totalComics' => $totalComics,
            'analyticsData' => [
                'daily' => $dailyAnalytics,
                'monthly' => $monthlyAnalytics,
                'annual' => $annualAnalytics
            ],
            'startDate' => $startDate,
            'endDate' => $endDate
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

    public function phpinfo(){
        return view('admin.phpinfo');
    }

}
