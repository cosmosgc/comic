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
        // Drop status files older than a day (keeps storage/app/deploy tidy).
        foreach (glob(storage_path('app/deploy/*.json')) ?: [] as $path) {
            if (filemtime($path) !== false && filemtime($path) < time() - 86400) {
                @unlink($path);
            }
        }

        [$runId, $runStatus] = $this->resolveDeployRun();

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
            'runId' => $runId,
            'runStatus' => $runStatus,
            'quickSince' => $deployer->lastSuccessAt(),
        ]);
    }

    /**
     * Resolve which run the panel should display. Flash data only survives
     * the redirect after starting, so on reload fall back to the active
     * run (lock) and then to the last started run (session) — no clicks.
     *
     * @return array{string|null, array|null}
     */
    protected function resolveDeployRun(): array
    {
        $candidate = session('deploy_run_id')
            ?? ($this->runningDeployRun()['id'] ?? null)
            ?? session('deploy_last_run_id');

        if (! is_string($candidate) || ! preg_match('/^[A-Za-z0-9]{32}$/', $candidate)) {
            return [null, null];
        }
        $path = $this->deployStatusPath($candidate);
        if (! is_file($path)) {
            return [null, null];
        }
        $status = json_decode((string) file_get_contents($path), true);

        return is_array($status) ? [$candidate, $status] : [null, null];
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
     * Start the FTP update as a background process and return immediately.
     * The web request never waits for the (slow) FTPS transfer — progress
     * is polled via deployStatus(). The worker re-verifies first and
     * aborts on any mismatch.
     */
    public function runDeploy(Request $request, FtpDeployer $deployer)
    {
        $request->validate([
            'confirm' => 'accepted',
        ]);
        $dryRun = $request->boolean('dry_run');
        $force = $request->boolean('force');
        $includeVendor = $request->boolean('include_vendor');
        // Quick when possible, full on first run or when forced.
        $full = $request->boolean('full');
        $quick = ! $full && ($request->boolean('quick') || $deployer->lastSuccessAt() !== null);

        if (! $deployer->isConfigured()) {
            return redirect()->route('admin.deploy')
                ->with('error', 'FTP is not configured. Missing: '.implode(', ', $deployer->missingConfig()));
        }

        if ($running = $this->runningDeployRun()) {
            return redirect()->route('admin.deploy')
                ->with('deploy_run_id', $running['id'])
                ->with('error', 'A deploy is already running — follow its progress below.');
        }

        $id = \Illuminate\Support\Str::random(32);
        $statusPath = $this->deployStatusPath($id);
        if (! is_dir(dirname($statusPath))) {
            mkdir(dirname($statusPath), 0777, true);
        }
        file_put_contents($statusPath, json_encode([
            'id' => $id,
            'state' => 'queued',
            'phase' => 'queued',
            'started_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'force' => $force,
            'include_vendor' => $includeVendor,
            'quick' => $quick && ! $full,
            'full' => $full,
            'done' => 0,
            'total' => null,
            'message' => null,
            'log' => [],
            'updated_at' => now()->toIso8601String(),
        ]));

        try {
            $this->spawnDeployWorker($statusPath, $dryRun, $force, $includeVendor, $quick, $full);
        } catch (\Throwable $e) {
            @unlink($statusPath);

            return redirect()->route('admin.deploy')
                ->with('error', 'Could not start the deploy worker: '.$e->getMessage());
        }

        // Persistent (not flash): reloads keep showing this run's progress.
        session()->put('deploy_last_run_id', $id);

        return redirect()->route('admin.deploy')
            ->with('deploy_run_id', $id)
            ->with('success', $dryRun ? 'Dry run started — progress below.' : 'Deploy started in the background — progress below.');
    }

    /**
     * Live progress for a background deploy run (polled by the view).
     */
    public function deployStatus(string $id)
    {
        // IDs are Str::random(32) (A-Za-z0-9), not hex.
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $id)) {
            abort(404);
        }
        $path = $this->deployStatusPath($id);
        if (! is_file($path)) {
            abort(404);
        }
        $status = json_decode((string) file_get_contents($path), true);

        return response()->json(is_array($status) ? $status : ['state' => 'unknown']);
    }

    protected function deployStatusPath(string $id): string
    {
        return storage_path('app/deploy/'.$id.'.json');
    }

    /**
     * Request cancellation of a running deploy. The worker observes the
     * flag and stops gracefully; a dead worker (stale heartbeat) is marked
     * cancelled outright so the lock releases immediately.
     */
    public function cancelDeploy(string $id)
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $id)) {
            abort(404);
        }
        $path = $this->deployStatusPath($id);
        if (! is_file($path)) {
            abort(404);
        }
        $status = json_decode((string) file_get_contents($path), true);
        if (! is_array($status)) {
            abort(404);
        }

        $state = $status['state'] ?? null;
        if (! in_array($state, ['queued', 'running', 'cancelling'], true)) {
            return redirect()->route('admin.deploy')
                ->with('deploy_run_id', $id)
                ->with('error', 'This run already finished — nothing to cancel.');
        }

        $updated = isset($status['updated_at']) ? strtotime($status['updated_at']) : 0;
        $stale = $updated === false || $updated < time() - 300;
        $status['cancel_requested'] = true;
        $status['updated_at'] = now()->toIso8601String();
        if ($stale) {
            // No heartbeat for 5+ minutes: the worker is dead, don't wait.
            $status['state'] = 'cancelled';
            $status['message'] = 'Cancelled (worker had stopped responding — no files were harmed).';
        } else {
            $status['state'] = 'cancelling';
            $status['message'] = 'Cancellation requested — the worker stops after the current file.';
        }
        file_put_contents($path, json_encode($status));

        return redirect()->route('admin.deploy')
            ->with('deploy_run_id', $id)
            ->with('success', $stale ? 'Dead run cleared.' : 'Cancellation requested.');
    }

    /**
     * @return array{id: string}|null the currently active run, if any
     */
    protected function runningDeployRun(): ?array
    {
        foreach (glob(storage_path('app/deploy/*.json')) ?: [] as $path) {
            $status = json_decode((string) @file_get_contents($path), true);
            if (! is_array($status) || ! in_array($status['state'] ?? null, ['queued', 'running', 'cancelling'], true)) {
                continue;
            }
            // Stale heartbeats (>30 min) are dead workers, not running ones.
            $updated = isset($status['updated_at']) ? strtotime($status['updated_at']) : 0;
            if ($updated !== false && $updated > time() - 1800) {
                return ['id' => basename($path, '.json')];
            }
            @unlink($path);
        }

        return null;
    }

    protected function spawnDeployWorker(string $statusPath, bool $dryRun, bool $force, bool $includeVendor, bool $quick, bool $full): void
    {
        $php = PHP_BINARY;
        if (str_ends_with(strtolower($php), 'php-cgi.exe')) {
            // php-cgi misbehaves for CLI jobs; use the CLI binary next to it.
            $php = substr($php, 0, -11).'php.exe';
        }
        $args = 'deploy:host --yes --no-interaction'
            .($dryRun ? ' --dry-run' : '')
            .($force ? ' --force' : '')
            .($includeVendor ? ' --with-vendor' : '')
            .($full ? ' --full' : ($quick ? ' --quick' : ''))
            .' --status-file='.escapeshellarg($statusPath);

        if (DIRECTORY_SEPARATOR === '\\') {
            // Detached on Windows; returns immediately.
            pclose(popen(
                'start /B "" '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' '.$args,
                'r'
            ));
        } else {
            exec(
                escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' '.$args
                .' > /dev/null 2>&1 &'
            );
        }
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
