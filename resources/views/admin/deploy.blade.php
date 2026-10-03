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

    {{-- Last result --}}
    @if ($result)
        <div class="card mb-4">
            <div class="card-header">Last run: {{ $result['message'] }}</div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;">{{ implode("\n", $result['log']) }}</pre>
            </div>
        </div>
    @endif
@endsection
