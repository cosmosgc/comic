@extends('layouts.admin-layout')

@section('title', 'Deploy')
@section('page-title', 'FTP Deploy')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif

    <p class="admin-muted mb-6 max-w-3xl">
        Pushes this project's code to the hosting provider over FTPS — no console needed on the host.
        Run this from your local copy. The update <strong>refuses to run</strong> unless the remote
        directory verifies as this Laravel project (see checks below). <code class="admin-code">.env</code>, uploads and
        caches are never overwritten, and nothing is ever deleted remotely.
    </p>

    {{-- Configuration --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>FTP configuration (.env)</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <tbody>
                    <tr>
                        <td>PHP cURL extension</td>
                        <td>
                            @if ($curlAvailable)
                                <span class="admin-badge admin-badge-success">Available</span>
                            @else
                                <span class="admin-badge admin-badge-danger">Missing — FTP deploy requires ext-curl</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td>FTP_HOST</td><td><code>{{ $host ?: '— not set —' }}</code></td></tr>
                    <tr><td>FTP_PORT</td><td><code>{{ $port }}</code> {{ $ssl ? '(FTPS)' : '(plain FTP)' }}</td></tr>
                    <tr><td>FTP_USERNAME</td><td><code>{{ $username ?: '— not set —' }}</code></td></tr>
                    <tr><td>FTP_PASSWORD</td><td>{{ $configured || !in_array('FTP_PASSWORD', $missing) ? '•••••••• (set)' : '— not set —' }}</td></tr>
                    <tr><td>FTP_ROOT (project dir)</td><td><code>{{ $root }}</code></td></tr>
                    <tr><td>FTP_PUBLIC_DIR (web root)</td><td><code>{{ $publicDir }}</code></td></tr>
                </tbody>
            </table>
        </div>
        @if (!$configured)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm text-red-700 dark:border-zinc-800 dark:text-red-300">
                Missing: <code class="admin-code">{{ implode(', ', $missing) }}</code>. Set them in <code class="admin-code">.env</code> (see <code class="admin-code">.env.example</code>).
            </div>
        @endif
    </div>

    {{-- Verify --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>1. Verify remote directory</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.deploy.verify') }}">
                @csrf
                <button type="submit" class="admin-btn admin-btn-secondary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                    Test connection &amp; verify project
                </button>
            </form>

            @if ($verification)
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <tbody>
                            @foreach ($verification['checks'] as $check)
                                <tr>
                                    <td>{{ $check['label'] }}</td>
                                    <td>
                                        @if ($check['ok'])
                                            <span class="admin-badge admin-badge-success">PASS</span>
                                        @else
                                            <span class="admin-badge admin-badge-danger">FAIL</span>
                                        @endif
                                    </td>
                                    <td class="admin-muted">{{ $check['detail'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (!empty($verification['root_entries']))
                    <div class="mt-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                        <strong>Remote directory contents ({{ count($verification['root_entries']) }}):</strong><br>
                        <code class="admin-code">{{ implode(', ', $verification['root_entries']) }}</code>
                        <br><span class="admin-muted">Use this list to set FTP_ROOT / FTP_PUBLIC_DIR correctly.</span>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Run --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>2. Run update</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.deploy.run') }}"
                  onsubmit="return confirm('Upload changed files to the host?')" class="space-y-2.5">
                @csrf
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="dry_run" id="dry_run" value="1" checked>
                    <span>Dry run (show what would be uploaded)</span>
                </label>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="force" id="force" value="1">
                    <span>Force re-upload of every file</span>
                </label>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="include_vendor">
                        <input type="checkbox" class="admin-check mt-0.5" name="include_vendor" id="include_vendor" value="1">
                        <span>Include vendor/ (~9,000 files, ~49 MB)</span>
                    </label>
                    <span class="admin-help ml-6">Skipped by default — vendor rarely changes. If the host has no vendor/ yet, it is included automatically.</span>
                </div>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="quick">
                        <input type="checkbox" class="admin-check mt-0.5" name="quick" id="quick" value="1" checked>
                        <span>Quick sync (only changed files)</span>
                    </label>
                    <span class="admin-help ml-6">
                        @if ($quickSince)
                            Since last success ({{ date('Y-m-d H:i', $quickSince) }}) — finishes in about a minute.
                        @else
                            No successful run yet — the first run is always a full walk.
                        @endif
                        Remote-side edits made outside deploys are only picked up by a full walk.
                    </span>
                </div>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="full" id="full" value="1">
                    <span>Full verification walk (overrides quick)</span>
                </label>
                <div>
                    <label class="flex items-start gap-2.5 text-sm" for="include_build">
                        <input type="checkbox" class="admin-check mt-0.5" name="include_build" id="include_build" value="1" checked>
                        <span>Include compiled assets (public/build/)</span>
                    </label>
                    <span class="admin-help ml-6">Uncheck for PHP-only changes — skips the Vite build output.</span>
                </div>
                <label class="flex items-start gap-2.5 text-sm">
                    <input type="checkbox" class="admin-check mt-0.5" name="confirm" id="confirm" value="1" required>
                    <span>I understand this uploads code to the live host</span>
                </label>
                <div class="pt-1">
                    <button type="submit" class="admin-btn admin-btn-primary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                        Run FTP update
                    </button>
                </div>
            </form>
            <p class="admin-muted mt-3">
                After the upload, open <a href="{{ route('admin.migrations') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Migrations</a> on the host
                and run any pending migrations.
            </p>
        </div>
    </div>

    {{-- Finished run (persists across reloads, no polling) --}}
    @if ($runId && $runStatus && in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled']))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Last run</span>
                @if (($runStatus['state'] ?? null) === 'done')
                    <span class="admin-badge admin-badge-success">done</span>
                @else
                    <span class="admin-badge admin-badge-danger">{{ $runStatus['state'] }}</span>
                @endif
            </div>
            <div class="admin-card-body space-y-2">
                @if (!empty($runStatus['message']))
                    <p>{{ $runStatus['message'] }}</p>
                @endif
                @if (isset($runStatus['done'], $runStatus['total']) && $runStatus['total'])
                    <p class="admin-muted">Sent {{ $runStatus['done'] }} of {{ $runStatus['total'] }} files.</p>
                @endif
                @if (!empty($runStatus['log']))
                    <pre id="deploy-result-log" class="admin-log">{{ implode("\n", array_slice($runStatus['log'], -80)) }}</pre>
                @endif
            </div>
        </div>
    @endif

    {{-- Live progress (background run) --}}
    @if ($runId && (! $runStatus || ! in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled'])))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Run progress <span class="admin-muted font-normal">(live — this page does not need to stay open)</span></span>
                <form method="POST" action="{{ route('admin.deploy.cancel', ['id' => $runId]) }}"
                      onsubmit="return confirm('Cancel this deploy run? Already-uploaded files stay in place.')">
                    @csrf
                    <button type="submit" id="deploy-cancel" class="admin-btn admin-btn-sm border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10">Cancel run</button>
                </form>
            </div>
            <div class="admin-card-body">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span id="deploy-phase" class="admin-badge admin-badge-secondary">queued</span>
                    <span id="deploy-modes" class="admin-muted"></span>
                </div>
                <div class="mb-4 h-6 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <div id="deploy-progress" class="flex h-full items-center justify-center text-xs font-semibold text-white transition-all duration-500"
                         role="progressbar" style="width: 0%;">0%</div>
                </div>
                <div class="mb-4 grid grid-cols-3 gap-2 text-center sm:grid-cols-6">
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Sent</div>
                        <div id="stat-sent" class="text-lg font-bold">0</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Total</div>
                        <div id="stat-total" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Remaining</div>
                        <div id="stat-remaining" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Elapsed</div>
                        <div id="stat-elapsed" class="text-lg font-bold">0s</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">Rate</div>
                        <div id="stat-rate" class="text-lg font-bold">—</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-2.5 dark:bg-zinc-800/60">
                        <div class="admin-muted text-xs">ETA</div>
                        <div id="stat-eta" class="text-lg font-bold">—</div>
                    </div>
                </div>
                <p class="admin-muted mb-1 text-xs">Currently uploading</p>
                <p id="deploy-current" class="mb-2"><code class="admin-code">—</code></p>
                <p id="deploy-counts" class="admin-muted mb-2 text-xs"></p>
                <p id="deploy-message" class="admin-muted mb-2">Starting…</p>
                <pre id="deploy-log" class="admin-log"></pre>
            </div>
        </div>
        <script>
            (function () {
                const url = "{{ route('admin.deploy.status', ['id' => $runId]) }}";
                const bar = document.getElementById('deploy-progress');
                const msg = document.getElementById('deploy-message');
                const log = document.getElementById('deploy-log');
                const phaseEl = document.getElementById('deploy-phase');
                const modesEl = document.getElementById('deploy-modes');
                const currentEl = document.getElementById('deploy-current');
                const countsEl = document.getElementById('deploy-counts');
                const statSent = document.getElementById('stat-sent');
                const statTotal = document.getElementById('stat-total');
                const statRemaining = document.getElementById('stat-remaining');
                const statElapsed = document.getElementById('stat-elapsed');
                const statRate = document.getElementById('stat-rate');
                const statEta = document.getElementById('stat-eta');
                const startMs = Date.now();

                function fmtDuration(sec) {
                    sec = Math.max(0, Math.floor(sec));
                    if (sec < 60) return sec + 's';
                    const m = Math.floor(sec / 60);
                    if (m < 60) return m + 'm ' + (sec % 60) + 's';
                    return Math.floor(m / 60) + 'h ' + (m % 60) + 'm';
                }

                const phaseLabels = {
                    queued: ['queued', 'admin-badge-secondary'],
                    listing: ['listing files', 'admin-badge-info'],
                    uploading: ['uploading', 'admin-badge-primary'],
                    done: ['done', 'admin-badge-success'],
                    failed: ['failed', 'admin-badge-danger'],
                    cancelling: ['cancelling', 'admin-badge-warning'],
                    cancelled: ['cancelled', 'admin-badge-warning'],
                };

                const timer = setInterval(async () => {
                    let res;
                    try {
                        res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                    } catch (e) {
                        msg.textContent = 'Lost contact with the worker — reload the page to check again.';
                        clearInterval(timer);
                        return;
                    }
                    const s = await res.json();
                    const total = s.total || 0;
                    const done = s.done || 0;
                    const remaining = total > 0 ? Math.max(0, total - done) : null;
                    const pct = total > 0 ? Math.round((done / total) * 100) : (s.state === 'done' ? 100 : 5);
                    bar.style.width = pct + '%';
                    bar.innerText = pct + '%';

                    statSent.textContent = done;
                    statTotal.textContent = total > 0 ? total : '—';
                    statRemaining.textContent = remaining === null ? '—' : remaining;

                    const elapsedSec = (Date.now() - startMs) / 1000;
                    statElapsed.textContent = fmtDuration(elapsedSec);
                    if (elapsedSec > 5 && done > 0 && (s.state === 'running')) {
                        const perSec = done / elapsedSec;
                        statRate.textContent = perSec >= 1
                            ? perSec.toFixed(1) + '/s'
                            : (perSec * 60).toFixed(1) + '/min';
                        statEta.textContent = remaining !== null && perSec > 0
                            ? fmtDuration(remaining / perSec)
                            : '—';
                    } else if (['done', 'failed', 'cancelled'].includes(s.state)) {
                        statRate.textContent = '—';
                        statEta.textContent = '0s';
                    }

                    const phase = s.phase || s.state || 'queued';
                    const [label, cls] = phaseLabels[phase] || [phase, 'admin-badge-secondary'];
                    phaseEl.textContent = label;
                    phaseEl.className = 'admin-badge ' + cls;

                    const modes = [];
                    if (s.dry_run) modes.push('dry run');
                    if (s.force) modes.push('force');
                    if (s.include_vendor) modes.push('with vendor');
                    if (s.quick) modes.push('quick');
                    if (s.full) modes.push('full');
                    if (s.include_build === false) modes.push('no build');
                    modesEl.textContent = modes.join(' • ');

                    currentEl.innerHTML = s.current ? '<code class="admin-code">' + s.current.replace(/</g, '&lt;') + '</code>' : '<code class="admin-code">—</code>';

                    const c = s.counts || {};
                    const parts = [];
                    if (c.skipped) parts.push(c.skipped + ' unchanged');
                    if (c.excluded) parts.push(c.excluded + ' excluded');
                    if (c.vendor_skipped) parts.push(c.vendor_skipped + ' vendor skipped');
                    if (c.quick_skipped) parts.push(c.quick_skipped + ' older than last success');
                    if (c.build_skipped) parts.push(c.build_skipped + ' build skipped');
                    if (c.quick) parts.push('quick mode');
                    countsEl.textContent = parts.join(' • ');

                    if (s.message) msg.textContent = s.message;
                    else if (s.state === 'running') msg.textContent = s.dry_run ? 'Dry run in progress…' : 'Uploading…';
                    else if (s.state === 'queued') msg.textContent = 'Worker starting…';
                    const lines = s.log || (s.log_tail ? [s.log_tail] : []);
                    if (lines.length) log.textContent = lines.slice(-80).join("\n");
                    const cancelBtn = document.getElementById('deploy-cancel');
                    if (s.state === 'done' || s.state === 'failed' || s.state === 'cancelled') {
                        clearInterval(timer);
                        bar.classList.add(s.state === 'done' ? 'is-done' : 'is-failed');
                        if (cancelBtn) cancelBtn.disabled = true;
                        if (s.state === 'cancelled' && s.message) msg.textContent = s.message + ' Re-run to resume.';
                    } else if (s.state === 'cancelling') {
                        if (cancelBtn) cancelBtn.disabled = true;
                    }
                }, 2000);
            })();
        </script>
    @endif
@endsection
