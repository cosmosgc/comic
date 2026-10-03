<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Comic;
use App\Models\Analytics;
use App\Models\Widget;

use Illuminate\Support\Facades\Hash;


use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\MigrationInspector;
use App\Services\Deploy\CurlFtpTransport;
use App\Services\Deploy\DeployException;
use App\Services\Deploy\FtpDeployer;
use Illuminate\Support\Facades\Artisan;

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

    /**
     * Show migration status: files on disk vs. tables in the database.
     * Works without console access — the Run button calls migrate via HTTP.
     */
    public function migrations(MigrationInspector $inspector)
    {
        $report = $inspector->report();

        return view('admin.migrations', ['report' => $report]);
    }

    /**
     * Run pending migrations from the admin panel (POST only).
     */
    public function runMigrations(Request $request)
    {
        $request->validate([
            'action' => 'required|in:migrate',
        ]);

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output() ?: '(no output)';
        } catch (\Throwable $e) {
            return redirect()->route('admin.migrations')
                ->with('error', 'Migration failed: '.$e->getMessage());
        }

        if ($exitCode !== 0) {
            return redirect()->route('admin.migrations')
                ->with('error', 'Migration exited with code '.$exitCode)
                ->with('migrate_output', $output);
        }

        return redirect()->route('admin.migrations')
            ->with('success', 'Migrations executed successfully.')
            ->with('migrate_output', $output);
    }

    /**
     * Show the FTP deploy panel (push code to the host without console).
     * Deploys are meant to run from a local/staging copy of this panel.
     */
    public function deploy(FtpDeployer $deployer)
    {
        return view('admin.deploy', [
            'configured' => $deployer->isConfigured(),
            'missing' => $deployer->missingConfig(),
            'curlAvailable' => function_exists('curl_init'),
            'host' => config('deploy.host'),
            'username' => config('deploy.username'),
            'port' => config('deploy.port'),
            'root' => config('deploy.root'),
            'publicDir' => config('deploy.public_dir'),
            'ssl' => (bool) config('deploy.ssl'),
            'verification' => session('deploy_verify'),
            'result' => session('deploy_result'),
        ]);
    }

    /**
     * Test the FTPS connection and verify the remote dir is this project.
     */
    public function verifyDeploy(FtpDeployer $deployer)
    {
        if (! $deployer->isConfigured()) {
            return redirect()->route('admin.deploy')
                ->with('error', 'FTP is not configured. Missing: '.implode(', ', $deployer->missingConfig()));
        }

        try {
            $transport = new CurlFtpTransport(config('deploy'));
            $verification = $deployer->verify($transport);
        } catch (DeployException $e) {
            return redirect()->route('admin.deploy')
                ->with('error', 'Connection failed: '.$e->getMessage());
        }

        return redirect()->route('admin.deploy')
            ->with('deploy_verify', $verification)
            ->with($verification['ok'] ? 'success' : 'error',
                $verification['ok']
                    ? 'Verification passed — the remote directory is this Laravel project.'
                    : 'Verification FAILED — the update will be refused until this is fixed.');
    }

    /**
     * Run the FTP update. Always re-verifies first; aborts on any mismatch.
     */
    public function runDeploy(Request $request, FtpDeployer $deployer)
    {
        $request->validate([
            'confirm' => 'accepted',
        ]);
        $dryRun = $request->boolean('dry_run');
        $force = $request->boolean('force');

        if (! $deployer->isConfigured()) {
            return redirect()->route('admin.deploy')
                ->with('error', 'FTP is not configured. Missing: '.implode(', ', $deployer->missingConfig()));
        }

        set_time_limit(0);

        try {
            $transport = new CurlFtpTransport(config('deploy'));
            $result = $deployer->sync($transport, $force, $dryRun);
        } catch (DeployException $e) {
            return redirect()->route('admin.deploy')
                ->with('error', 'Deploy failed: '.$e->getMessage());
        }

        return redirect()->route('admin.deploy')
            ->with('deploy_verify', null)
            ->with('deploy_result', $result)
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
    public function widgets()
    {
        $widgets = Widget::orderBy('position_index')->get();
        return view('admin.widgets', compact('widgets'));
    }

    // Store new widget
    public function storeWidget(Request $request)
    {
        $request->validate([
            'position_index' => 'required|integer',
            'content' => 'required|string',
        ]);

        Widget::create($request->only('title', 'position_index', 'content'));

        return redirect()->route('admin.widgets')->with('success', 'Widget created successfully!');
    }

    // Show edit form
    public function editWidget($id)
    {
        $widget = Widget::findOrFail($id);
        return view('admin.widget-edit', compact('widget'));
    }

    // Update widget
    public function updateWidget(Request $request, $id)
    {
        $widget = Widget::findOrFail($id);

        $request->validate([
            'position_index' => 'required|integer',
            'content' => 'required|string',
        ]);

        $widget->update($request->only('title', 'position_index', 'content'));

        return redirect()->route('admin.widgets')->with('success', 'Widget updated!');
    }

    // Delete widget
    public function destroyWidget($id)
    {
        Widget::destroy($id);
        return redirect()->route('admin.widgets')->with('success', 'Widget deleted!');
    }

}
