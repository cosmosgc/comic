<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use App\Models\Comic;
use App\Models\ComicTransfer;
use App\Models\Page;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Models\Widget;
use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use App\Services\ComicPackageException;
use App\Services\ComicPackageService;
use App\Services\ComicTransferService;
use App\Services\Deploy\CurlFtpTransport;
use App\Services\Deploy\DeployException;
use App\Services\Deploy\FtpDeployer;
use App\Services\GithubPullRequests;
use App\Services\MigrationInspector;
use App\Services\TransferException;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                'annual' => $annualAnalytics,
            ],
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function analytics()
    {
        $analytics = Analytics::latest()->paginate(1000); // Show recent events

        return view('admin.analytics', compact('analytics'));
    }

    public function comics()
    {
        $comics = Comic::with('transfer:id,comic_id,status,remote_comic_id,remote_view_url,next_page,total_pages')->get();

        return view('admin.comics', [
            'comics' => $comics,
            'transferConfigured' => trim((string) config('transfer.remote_url')) !== ''
                && trim((string) config('transfer.remote_token')) !== '',
        ]);
    }

    /**
     * Delete a comic with everything attached: pages, tags/likes pivots,
     * comments and import tracking go via FK cascades; the cover + page
     * files under storage/comics/{id} are removed from disk.
     */
    public function destroyComic(Comic $comic)
    {
        File::deleteDirectory(public_path("storage/comics/{$comic->id}"));
        $title = $comic->title;
        $comic->delete();

        return redirect()->route('admin.comics')->with('success', "Comic \"{$title}\" excluído com todos os arquivos.");
    }

    /**
     * Start (or resume) a server-to-server transfer: creates the remote
     * shell on first run, then the view polls pushTransfer per chunk.
     * Resumes re-push from page one (remote upserts by page number).
     */
    public function transferComic(Comic $comic, ComicTransferService $sender)
    {
        $transfer = $comic->transfer;
        if ($transfer !== null && $transfer->isDone()) {
            return response()->json(['message' => 'Já transferido.'], 409);
        }

        $pages = $comic->pages()->orderBy('page_number')->get(['id', 'page_number', 'image_path'])
            ->filter(fn (Page $page) => is_file(public_path('storage/'.$page->image_path)))
            ->values();
        if ($pages->isEmpty()) {
            return response()->json(['message' => 'Sem arquivos de página para enviar.'], 422);
        }

        try {
            if ($transfer === null) {
                $remoteId = $sender->createRemote($comic->fresh());
                $transfer = ComicTransfer::create([
                    'comic_id' => $comic->id,
                    'remote_url' => trim((string) config('transfer.remote_url')),
                    'status' => ComicTransfer::STATUS_SENDING,
                    'remote_comic_id' => $remoteId,
                    'next_page' => 1,
                    'total_pages' => $pages->count(),
                ]);
            } else {
                $transfer->update([
                    'status' => ComicTransfer::STATUS_SENDING,
                    'error' => null,
                    'next_page' => 1,
                    'total_pages' => $pages->count(),
                ]);
            }
        } catch (TransferException|ConnectionException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'transfer_id' => $transfer->id,
            'push_url' => route('admin.transfers.push', $transfer),
            'total' => $transfer->total_pages,
            'next' => $transfer->next_page,
        ]);
    }

    /**
     * Push the next chunk of pages to the remote site (polled as JSON).
     * Pages already stored remotely are skipped, so retries after an
     * interruption only send what's missing.
     */
    public function pushTransfer(ComicTransfer $transfer, ComicTransferService $sender)
    {
        if ($transfer->isDone()) {
            return response()->json($this->transferPayload($transfer, true, $transfer->remote_view_url));
        }

        try {
            $remotePages = $sender->remotePages($transfer);
        } catch (TransferException|ConnectionException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $comic = $transfer->comic;
        $pages = $comic->pages()->orderBy('page_number')->get(['id', 'page_number', 'image_path']);
        $skipped = 0;
        $processed = 0;
        $chunk = max(1, (int) config('transfer.chunk', 2));

        while ($processed < $chunk) {
            $page = $pages->firstWhere('page_number', $transfer->next_page);
            if ($page === null) {
                break;
            }
            $transfer->increment('next_page');
            if (in_array($page->page_number, $remotePages, true)) {
                $skipped++;

                continue;
            }
            $full = public_path('storage/'.$page->image_path);
            if (! is_file($full)) {
                $skipped++;

                continue;
            }
            try {
                $sender->pushPage($transfer->fresh(), $page, $full);
            } catch (TransferException|ConnectionException $e) {
                $transfer->update(['error' => mb_substr($e->getMessage(), 0, 500)]);

                return response()->json(['message' => $e->getMessage()], 502);
            }
            $processed++;
        }

        $transfer->update(['error' => null]);

        if ($transfer->fresh()->next_page > $transfer->total_pages) {
            try {
                $final = $sender->finalize($transfer->fresh());
            } catch (TransferException|ConnectionException $e) {
                $transfer->update(['error' => mb_substr($e->getMessage(), 0, 500)]);

                return response()->json(['message' => $e->getMessage()], 502);
            }
            $transfer->update(['status' => ComicTransfer::STATUS_DONE, 'remote_view_url' => $final['url'] ?? null]);

            return response()->json($this->transferPayload($transfer->fresh(), true, $final['url'] ?? null));
        }

        return response()->json($this->transferPayload($transfer->fresh(), false));
    }

    /**
     * @return array{done: int, total: int, complete: bool, url?: string|null, skipped?: int}
     */
    protected function transferPayload(ComicTransfer $transfer, bool $complete, ?string $url = null): array
    {
        $payload = [
            'done' => min($transfer->next_page - 1, $transfer->total_pages),
            'total' => $transfer->total_pages,
            'complete' => $complete,
        ];
        if ($url !== null) {
            $payload['url'] = $url;
        }

        return $payload;
    }

    /**
     * Download a transfer package (.zip with manifest + images) to
     * import on another installation (e.g. local → remote).
     */
    public function exportComicPackage(Comic $comic, ComicPackageService $packages)
    {
        try {
            $path = $packages->export($comic);
        } catch (ComicPackageException $e) {
            return redirect()->route('admin.comics')->with('error', $e->getMessage());
        }

        return response()->download($path, "comic-{$comic->id}-{$comic->slug}.zip")->deleteFileAfterSend(true);
    }

    /**
     * Rebuild a comic from an uploaded transfer package (fresh IDs,
     * tags resolved, cover + order preserved).
     */
    public function importComicPackage(Request $request, ComicPackageService $packages)
    {
        $request->validate(['package' => 'required|file|mimes:zip|max:512000']);

        try {
            $comic = $packages->import($request->file('package')->getRealPath(), auth()->id());
        } catch (ComicPackageException $e) {
            return redirect()->route('admin.comics')->with('error', $e->getMessage());
        }

        return redirect()->route('comics.edit', $comic->id)
            ->with('success', "Pacote importado: {$comic->title} ({$comic->pages()->count()} página(s)).");
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
            'email' => 'required|string|email|max:255|unique:users,email,'.$id,
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

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    // Method to delete a user
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting the currently authenticated user
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')->with('error', 'Você não pode excluir a si mesmo.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuário excluído com sucesso.');
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
                ->with('error', 'Migração falhou: '.$e->getMessage());
        }

        if ($exitCode !== 0) {
            return redirect()->route('admin.migrations')
                ->with('error', 'Migrate saiu com código '.$exitCode)
                ->with('migrate_output', $output);
        }

        return redirect()->route('admin.migrations')
            ->with('success', 'Migrações executadas com sucesso.')
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
                ->with('error', 'FTP não configurado. Faltando: '.implode(', ', $deployer->missingConfig()));
        }

        try {
            $transport = new CurlFtpTransport(config('deploy'));
            $verification = $deployer->verify($transport);
        } catch (DeployException $e) {
            return redirect()->route('admin.deploy')
                ->with('error', 'Conexão falhou: '.$e->getMessage());
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
        // Checkbox is checked by default in the form; unchecked submits
        // nothing, which excludes the compiled assets (PHP-only change).
        $includeBuild = $request->boolean('include_build');
        // Quick when possible, full on first run or when forced.
        $full = $request->boolean('full');
        $quick = ! $full && ($request->boolean('quick') || $deployer->lastSuccessAt() !== null);

        if (! $deployer->isConfigured()) {
            return redirect()->route('admin.deploy')
                ->with('error', 'FTP não configurado. Faltando: '.implode(', ', $deployer->missingConfig()));
        }

        if ($running = $this->runningDeployRun()) {
            return redirect()->route('admin.deploy')
                ->with('deploy_run_id', $running['id'])
                ->with('error', 'Já há um deploy rodando — acompanhe abaixo.');
        }

        $id = Str::random(32);
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
            'include_build' => $includeBuild,
            'quick' => $quick && ! $full,
            'full' => $full,
            'done' => 0,
            'total' => null,
            'message' => null,
            'log' => [],
            'updated_at' => now()->toIso8601String(),
        ]));

        try {
            $this->spawnDeployWorker($statusPath, $dryRun, $force, $includeVendor, $quick, $full, $includeBuild);
        } catch (\Throwable $e) {
            @unlink($statusPath);

            return redirect()->route('admin.deploy')
                ->with('error', 'Não foi possível iniciar o worker: '.$e->getMessage());
        }

        // Persistent (not flash): reloads keep showing this run's progress.
        session()->put('deploy_last_run_id', $id);

        return redirect()->route('admin.deploy')
            ->with('deploy_run_id', $id)
            ->with('success', $dryRun ? 'Simulação iniciada — progresso abaixo.' : 'Deploy iniciado em segundo plano — progresso abaixo.');
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
                ->with('error', 'Esta execução já terminou — nada a cancelar.');
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
            ->with('success', $stale ? 'Execução morta limpa.' : 'Cancelamento solicitado.');
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

    protected function spawnDeployWorker(string $statusPath, bool $dryRun, bool $force, bool $includeVendor, bool $quick, bool $full, bool $includeBuild): void
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
            .($includeBuild ? '' : ' --skip-build')
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

    /**
     * What's-new manager: GitHub PRs missing a changelog entry, plus the
     * existing entries for editing. Public page lives at /changelog.
     */
    public function changelogs(
        ChangelogReader $reader,
        GithubPullRequests $github
    ) {
        $entries = $reader->all();
        $used = $reader->prsUsed();

        $pullRequests = [];
        $fetchError = null;
        try {
            $pullRequests = $github->fetch(
                (string) config('changelog.github_repo'),
                config('changelog.github_token') ?: null,
                (int) config('changelog.github_per_page', 30)
            );
        } catch (\Throwable $e) {
            $fetchError = $e->getMessage();
        }

        $missing = array_values(array_filter(
            $pullRequests,
            fn ($pr) => ! in_array($pr['number'], $used, true)
        ));

        return view('admin.changelogs', [
            'entries' => $entries,
            'pullRequests' => $pullRequests,
            'missing' => $missing,
            'fetchError' => $fetchError,
            'repo' => config('changelog.github_repo'),
        ]);
    }

    /**
     * Import a PR as a draft entry, then land on the edit form.
     */
    public function changelogImport(
        Request $request,
        ChangelogReader $reader,
        ChangelogWriter $writer,
        GithubPullRequests $github
    ) {
        $request->validate(['number' => 'required|integer|min:1']);
        $number = (int) $request->input('number');

        if (in_array($number, $reader->prsUsed(), true)) {
            return redirect()->route('admin.changelogs')
                ->with('error', "PR #{$number} já tem entrada no changelog.");
        }

        try {
            $pullRequests = $github->fetch(
                (string) config('changelog.github_repo'),
                config('changelog.github_token') ?: null,
                (int) config('changelog.github_per_page', 30)
            );
        } catch (\Throwable $e) {
            return redirect()->route('admin.changelogs')
                ->with('error', 'Não foi possível buscar os PRs: '.$e->getMessage());
        }

        $pr = collect($pullRequests)->firstWhere('number', $number);
        if ($pr === null) {
            return redirect()->route('admin.changelogs')
                ->with('error', "PR #{$number} não encontrado na lista recente.");
        }

        $id = $writer->save([
            'pr' => $pr['number'],
            'title' => $pr['title'],
            'date' => now()->format('Y-m-d'),
            'category' => 'Added',
            'tags' => [],
            'summary' => '',
            'body' => $pr['body'],
        ]);

        return redirect()->route('admin.changelogs.edit', $id)
            ->with('success', "PR #{$number} importado — revise e salve.");
    }

    public function changelogCreate()
    {
        return view('admin.changelog-form', [
            'entry' => null,
            'entryId' => null,
            'categories' => config('changelog.categories'),
        ]);
    }

    public function changelogEdit(string $id, ChangelogReader $reader)
    {
        $entry = $reader->find($id);
        abort_if($entry === null, 404);

        return view('admin.changelog-form', [
            'entry' => $entry,
            'entryId' => $id,
            'categories' => config('changelog.categories'),
        ]);
    }

    public function changelogStore(
        Request $request,
        ChangelogWriter $writer
    ) {
        [$data, $errors] = $writer->validate($request->all(), config('changelog.categories', []));
        if ($errors !== []) {
            return redirect()->route('admin.changelogs.create')
                ->withErrors($errors)
                ->withInput();
        }

        $id = $writer->save($data);

        return redirect()->route('admin.changelogs.edit', $id)
            ->with('success', 'Entrada criada.');
    }

    public function changelogUpdate(
        Request $request,
        string $id,
        ChangelogReader $reader,
        ChangelogWriter $writer
    ) {
        abort_if($reader->find($id) === null, 404);

        [$data, $errors] = $writer->validate($request->all(), config('changelog.categories', []));
        if ($errors !== []) {
            return redirect()->route('admin.changelogs.edit', $id)
                ->withErrors($errors)
                ->withInput();
        }

        $writer->save($data, $id);

        return redirect()->route('admin.changelogs.edit', $id)
            ->with('success', 'Entrada salva.');
    }

    public function changelogDestroy(
        string $id,
        ChangelogReader $reader,
        ChangelogWriter $writer
    ) {
        abort_if($reader->find($id) === null, 404);
        $writer->delete($id);

        return redirect()->route('admin.changelogs')
            ->with('success', 'Entrada excluída.');
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

        return redirect()->route('admin.widgets')->with('success', 'Widget criado com sucesso!');
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

        return redirect()->route('admin.widgets')->with('success', 'Widget atualizado!');
    }

    // Delete widget
    public function destroyWidget($id)
    {
        Widget::destroy($id);

        return redirect()->route('admin.widgets')->with('success', 'Widget excluído!');
    }

    /**
     * Tag categories: color-coded groups (artist, character, copyright,
     * species, general, meta, lore) with keyword/namespace mapping.
     */
    public function tagCategories()
    {
        $categories = TagCategory::withCount('tags')->orderBy('sort_order')->orderBy('name')->get();
        $uncategorized = Tag::whereNull('category_id')->count();

        return view('admin.tag-categories', compact('categories', 'uncategorized'));
    }

    public function tagCategoryCreate()
    {
        return view('admin.tag-category-form', ['category' => null]);
    }

    public function tagCategoryStore(Request $request)
    {
        $validated = $this->validateTagCategory($request);

        TagCategory::create($validated);

        return redirect()->route('admin.tag-categories')->with('success', 'Categoria criada.');
    }

    public function tagCategoryEdit($id)
    {
        $category = TagCategory::findOrFail($id);

        return view('admin.tag-category-form', compact('category'));
    }

    public function tagCategoryUpdate(Request $request, $id)
    {
        $category = TagCategory::findOrFail($id);
        $validated = $this->validateTagCategory($request, $category->id);

        $category->update($validated);

        return redirect()->route('admin.tag-categories')->with('success', 'Categoria atualizada.');
    }

    public function tagCategoryDestroy($id)
    {
        $category = TagCategory::findOrFail($id);

        // Orphaned tags fall back to the "general" style at render time.
        $fallback = TagCategory::where('name', 'general')->where('id', '!=', $category->id)->first();
        Tag::where('category_id', $category->id)->update(['category_id' => $fallback?->id]);
        $category->delete();

        return redirect()->route('admin.tag-categories')->with('success', 'Categoria excluída.');
    }

    /**
     * Flip a category's public visibility. Hidden categories keep
     * their tags stored (search + editor unaffected).
     */
    public function tagCategoryToggle($id)
    {
        $category = TagCategory::findOrFail($id);
        $category->update(['is_visible' => ! $category->is_visible]);

        return redirect()->route('admin.tag-categories')->with(
            'success',
            $category->is_visible
                ? "Categoria {$category->label} visível novamente."
                : "Categoria {$category->label} oculta do site."
        );
    }

    /**
     * Forget what namespaced imports taught this category (its learned
     * vocabulary). Curated keywords are untouched.
     */
    public function tagCategoryClearLearned($id)
    {
        $category = TagCategory::findOrFail($id);
        $category->update(['learned_keywords' => []]);

        return redirect()->route('admin.tag-categories')->with(
            'success',
            "Vocabulário aprendido de {$category->label} apagado."
        );
    }

    /**
     * Re-run tag normalization over every tag: strips EH namespace
     * prefixes, merges twins, and reapplies categories (creating
     * unknown namespace categories). Picks up edited keywords,
     * namespaces, or colors without touching anything else.
     */
    public function tagCategoryRecategorize()
    {
        $names = Tag::orderBy('id')->pluck('name');
        $changed = 0;

        foreach ($names as $name) {
            $original = Tag::where('name', $name)->first();
            if ($original === null) {
                continue; // merged away by an earlier twin
            }

            $resolved = Tag::findOrCreateResolved($name, true)->fresh();

            if ($resolved->id !== $original->id
                || $resolved->name !== $original->name
                || $resolved->category_id !== $original->category_id) {
                $changed++;
            }
        }

        return redirect()->route('admin.tag-categories')
            ->with('success', "Recategorização concluída — {$changed} tag(s) atualizada(s).");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateTagCategory(Request $request, ?int $ignoreId = null): array
    {
        $hex = 'required|string|regex:/^#[0-9a-fA-F]{6}$/|max:7';

        $validated = $request->validate([
            'name' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/|unique:tag_categories,name,'.$ignoreId,
            'label' => 'required|string|max:50',
            'background' => $hex,
            'border' => $hex,
            'text_color' => $hex,
            'namespaces' => 'nullable|string|max:2000',
            'keywords' => 'nullable|string|max:5000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        return [
            'name' => mb_strtolower($validated['name']),
            'label' => $validated['label'],
            'background' => $validated['background'],
            'border' => $validated['border'],
            'text_color' => $validated['text_color'],
            'namespaces' => self::splitList($validated['namespaces'] ?? null),
            'keywords' => self::splitList($validated['keywords'] ?? null),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_visible' => $request->boolean('is_visible'),
        ];
    }

    /**
     * Split a comma/newline-separated admin input into a clean list.
     * Spaces are preserved inside entries ("live action" stays whole).
     *
     * @return array<int, string>
     */
    protected static function splitList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($part) => mb_strtolower(trim($part)),
            preg_split('/[,\r\n]+/', $raw) ?: []
        ))));
    }
}
