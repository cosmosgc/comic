@extends('layouts.admin-layout')

@section('title', 'Deploy')
@section('page-title', 'FTP Deploy')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <p class="text-muted">
        Pushes this project's code to the hosting provider over FTPS — no console needed on the host.
        Run this from your local copy. The update <strong>refuses to run</strong> unless the remote
        directory verifies as this Laravel project (see checks below). <code>.env</code>, uploads and
        caches are never overwritten, and nothing is ever deleted remotely.
    </p>

    {{-- Configuration --}}
    <div class="card mb-4">
        <div class="card-header">FTP configuration (.env)</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <tbody>
                    <tr>
                        <td>PHP cURL extension</td>
                        <td>
                            @if ($curlAvailable)
                                <span class="badge badge-success">Available</span>
                            @else
                                <span class="badge badge-danger">Missing — FTP deploy requires ext-curl</span>
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
            <div class="card-footer text-danger">
                Missing: <code>{{ implode(', ', $missing) }}</code>. Set them in <code>.env</code> (see <code>.env.example</code>).
            </div>
        @endif
    </div>

    {{-- Verify --}}
    <div class="card mb-4">
        <div class="card-header">1. Verify remote directory</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.deploy.verify') }}">
                @csrf
                <button type="submit" class="btn btn-secondary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                    Test connection &amp; verify project
                </button>
            </form>

            @if ($verification)
                <table class="table table-striped mt-3 mb-0">
                    <tbody>
                        @foreach ($verification['checks'] as $check)
                            <tr>
                                <td>{{ $check['label'] }}</td>
                                <td>
                                    @if ($check['ok'])
                                        <span class="badge badge-success">PASS</span>
                                    @else
                                        <span class="badge badge-danger">FAIL</span>
                                    @endif
                                </td>
                                <td><small>{{ $check['detail'] }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (!empty($verification['root_entries']))
                    <div class="p-3 border-top">
                        <strong>Remote directory contents ({{ count($verification['root_entries']) }}):</strong><br>
                        <small><code>{{ implode(', ', $verification['root_entries']) }}</code></small>
                        <br><small class="text-muted">Use this list to set FTP_ROOT / FTP_PUBLIC_DIR correctly.</small>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Run --}}
    <div class="card mb-4">
        <div class="card-header">2. Run update</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.deploy.run') }}"
                  onsubmit="return confirm('Upload changed files to the host?')">
                @csrf
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="dry_run" id="dry_run" value="1" checked>
                    <label class="form-check-label" for="dry_run">Dry run (show what would be uploaded)</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="force" id="force" value="1">
                    <label class="form-check-label" for="force">Force re-upload of every file</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="include_vendor" id="include_vendor" value="1">
                    <label class="form-check-label" for="include_vendor">Include vendor/ (~9,000 files, ~49 MB)</label>
                    <small class="form-text text-muted d-block">Skipped by default — vendor rarely changes. If the host has no vendor/ yet, it is included automatically.</small>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="quick" id="quick" value="1" checked>
                    <label class="form-check-label" for="quick">Quick sync (only changed files)</label>
                    <small class="form-text text-muted d-block">
                        @if ($quickSince)
                            Since last success ({{ date('Y-m-d H:i', $quickSince) }}) — finishes in about a minute.
                        @else
                            No successful run yet — the first run is always a full walk.
                        @endif
                        Remote-side edits made outside deploys are only picked up by a full walk.
                    </small>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="full" id="full" value="1">
                    <label class="form-check-label" for="full">Full verification walk (overrides quick)</label>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="confirm" id="confirm" value="1" required>
                    <label class="form-check-label" for="confirm">I understand this uploads code to the live host</label>
                </div>
                <button type="submit" class="btn btn-primary" {{ !$configured || !$curlAvailable ? 'disabled' : '' }}>
                    Run FTP update
                </button>
            </form>
            <p class="mt-3 mb-0 text-muted">
                After the upload, open <a href="{{ route('admin.migrations') }}">Migrations</a> on the host
                and run any pending migrations.
            </p>
        </div>
    </div>

    {{-- Finished run (persists across reloads, no polling) --}}
    @if ($runId && $runStatus && in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled']))
        <div class="card mb-4">
            <div class="card-header">
                Last run
                @if (($runStatus['state'] ?? null) === 'done')
                    <span class="badge badge-success">done</span>
                @else
                    <span class="badge badge-danger">{{ $runStatus['state'] }}</span>
                @endif
            </div>
            <div class="card-body">
                @if (!empty($runStatus['message']))
                    <p class="mb-2">{{ $runStatus['message'] }}</p>
                @endif
                @if (isset($runStatus['done'], $runStatus['total']) && $runStatus['total'])
                    <p class="mb-2 text-muted small">Sent {{ $runStatus['done'] }} of {{ $runStatus['total'] }} files.</p>
                @endif
                @if (!empty($runStatus['log']))
                    <pre id="deploy-result-log" class="mb-0" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;">{{ implode("\n", array_slice($runStatus['log'], -80)) }}</pre>
                @endif
            </div>
        </div>
    @endif

    {{-- Live progress (background run) --}}
    @if ($runId && (! $runStatus || ! in_array($runStatus['state'] ?? null, ['done', 'failed', 'cancelled'])))
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Run progress <small class="text-muted">(live — this page does not need to stay open)</small></span>
                <form method="POST" action="{{ route('admin.deploy.cancel', ['id' => $runId]) }}"
                      onsubmit="return confirm('Cancel this deploy run? Already-uploaded files stay in place.')">
                    @csrf
                    <button type="submit" id="deploy-cancel" class="btn btn-sm btn-outline-danger">Cancel run</button>
                </form>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span id="deploy-phase" class="badge badge-secondary">queued</span>
                    <span id="deploy-modes" class="ml-2"></span>
                </div>
                <div class="progress mb-3" style="height: 25px;">
                    <div id="deploy-progress" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%;">0%</div>
                </div>
                <div class="row text-center mb-3">
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">Sent</div>
                        <div id="stat-sent" class="h5 mb-0">0</div>
                    </div>
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">Total</div>
                        <div id="stat-total" class="h5 mb-0">—</div>
                    </div>
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">Remaining</div>
                        <div id="stat-remaining" class="h5 mb-0">—</div>
                    </div>
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">Elapsed</div>
                        <div id="stat-elapsed" class="h5 mb-0">0s</div>
                    </div>
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">Rate</div>
                        <div id="stat-rate" class="h5 mb-0">—</div>
                    </div>
                    <div class="col-4 col-md-2 mb-2">
                        <div class="text-muted small">ETA</div>
                        <div id="stat-eta" class="h5 mb-0">—</div>
                    </div>
                </div>
                <p class="mb-1 small text-muted">Currently uploading</p>
                <p id="deploy-current" class="mb-2"><code>—</code></p>
                <p id="deploy-counts" class="mb-2 small text-muted"></p>
                <p id="deploy-message" class="mb-2 text-muted">Starting…</p>
                <pre id="deploy-log" class="mb-0" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></pre>
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
                    queued: ['queued', 'badge-secondary'],
                    listing: ['listing files', 'badge-info'],
                    uploading: ['uploading', 'badge-primary'],
                    done: ['done', 'badge-success'],
                    failed: ['failed', 'badge-danger'],
                    cancelling: ['cancelling', 'badge-warning'],
                    cancelled: ['cancelled', 'badge-warning'],
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
                    const [label, cls] = phaseLabels[phase] || [phase, 'badge-secondary'];
                    phaseEl.textContent = label;
                    phaseEl.className = 'badge ' + cls;

                    const modes = [];
                    if (s.dry_run) modes.push('dry run');
                    if (s.force) modes.push('force');
                    if (s.include_vendor) modes.push('with vendor');
                    if (s.quick) modes.push('quick');
                    if (s.full) modes.push('full');
                    modesEl.textContent = modes.join(' • ');

                    currentEl.innerHTML = s.current ? '<code>' + s.current.replace(/</g, '&lt;') + '</code>' : '<code>—</code>';

                    const c = s.counts || {};
                    const parts = [];
                    if (c.skipped) parts.push(c.skipped + ' unchanged');
                    if (c.excluded) parts.push(c.excluded + ' excluded');
                    if (c.vendor_skipped) parts.push(c.vendor_skipped + ' vendor skipped');
                    if (c.quick_skipped) parts.push(c.quick_skipped + ' older than last success');
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
                        bar.classList.remove('progress-bar-animated');
                        bar.classList.add(s.state === 'done' ? 'bg-success' : 'bg-danger');
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
