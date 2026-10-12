@extends('layouts.admin-layout')

@section('title', 'Migrations')
@section('page-title', 'Migrations')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('migrate_output'))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Last migrate output</span>
            </div>
            <div class="admin-card-body">
                <pre class="admin-log">{{ session('migrate_output') }}</pre>
            </div>
        </div>
    @endif

    {{-- Status overview --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="admin-stat">
            <div class="admin-stat-label">Connection</div>
            <div class="mt-1 text-xl font-bold">{{ $report['connection'] }} <span class="text-sm font-medium text-zinc-500">({{ $report['driver'] }})</span></div>
            <div class="admin-stat-sub">{{ $report['database'] }}</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Migration files</div>
            <div class="admin-stat-value">{{ count($report['files']) }}</div>
            <div class="admin-stat-sub">{{ count($report['ran']) }} ran · {{ count($report['pending']) }} pending</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Tables</div>
            <div class="admin-stat-value">{{ count($report['actualTables']) }}</div>
            <div class="admin-stat-sub">{{ count($report['expectedTables']) }} expected · {{ count($report['missingTables']) }} missing · {{ count($report['extraTables']) }} extra</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Health</div>
            <div class="mt-2">
                @if ($report['healthy'])
                    <span class="admin-badge admin-badge-success">Up to date</span>
                @else
                    <span class="admin-badge admin-badge-danger">Needs attention</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Problems callout --}}
    @if (!$report['healthy'])
        <div class="admin-alert admin-alert-warning mt-6">
            <strong>Something is off:</strong>
            <ul>
                @foreach ($report['pending'] as $name)
                    <li>Pending migration: <code class="admin-code">{{ $name }}</code></li>
                @endforeach
                @foreach ($report['missingTables'] as $table)
                    <li>Missing table: <code class="admin-code">{{ $table }}</code> (expected by a migration, not in DB)</li>
                @endforeach
                @foreach ($report['ranWithoutFile'] as $name)
                    <li>Ran but file missing: <code class="admin-code">{{ $name }}</code> (DB says ran, no file on disk — was it deleted?)</li>
                @endforeach
                @foreach ($report['tables'] as $table => $info)
                    @if (!empty($info['missingColumns']))
                        <li>Table <code class="admin-code">{{ $table }}</code> missing columns: <code class="admin-code">{{ implode(', ', $info['missingColumns']) }}</code></li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Run migrations (no console needed) --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Run migrations</span>
        </div>
        <div class="admin-card-body">
            @if (count($report['pending']) > 0)
                <p class="admin-muted mb-3">Runs <code class="admin-code">php artisan migrate --force</code> on the server via this panel. No SSH needed.</p>
                <form method="POST" action="{{ route('admin.migrations.run') }}" onsubmit="return confirm('Run {{ count($report['pending']) }} pending migration(s)?')">
                    @csrf
                    <input type="hidden" name="action" value="migrate">
                    <button type="submit" class="admin-btn admin-btn-primary">Run {{ count($report['pending']) }} pending migration(s)</button>
                </form>
            @else
                <p class="admin-muted mb-0">Database is up to date — nothing to run.</p>
            @endif
        </div>
    </div>

    {{-- Pending migrations --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Pending migrations ({{ count($report['pending']) }})</span>
        </div>
        @if (count($report['pending']) === 0)
            <p class="admin-muted px-5 py-4">None — all files have run.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Migration</th>
                            <th>Creates</th>
                            <th>Alters</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['files'] as $file)
                            @if (!$file['ran'])
                                <tr>
                                    <td><code>{{ $file['name'] }}</code></td>
                                    <td><code>{{ implode(', ', $file['tablesCreated']) ?: '—' }}</code></td>
                                    <td><code>{{ implode(', ', $file['tablesAltered']) ?: '—' }}</code></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Tables: expected vs actual --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Tables: migrations vs database</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Columns (DB)</th>
                        <th>Missing columns</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['tables'] as $table => $info)
                        <tr>
                            <td><code>{{ $table }}</code></td>
                            <td>
                                @if (!$info['exists'])
                                    <span class="admin-badge admin-badge-danger">Missing in DB</span>
                                @elseif (!$info['expected'])
                                    <span class="admin-badge admin-badge-warning">Extra (no migration)</span>
                                @else
                                    <span class="admin-badge admin-badge-success">OK</span>
                                @endif
                            </td>
                            <td>{{ $info['rows'] ?? '—' }}</td>
                            <td>{{ count($info['columns']) }}</td>
                            <td>
                                @if (!empty($info['missingColumns']))
                                    <code class="text-red-600 dark:text-red-400">{{ implode(', ', $info['missingColumns']) }}</code>
                                @else
                                    <span class="admin-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Per-table column detail --}}
    @foreach ($report['tables'] as $table => $info)
        @if ($info['exists'])
            <div class="admin-card mt-4">
                <div class="admin-card-header">
                    <span><code>{{ $table }}</code>
                    <span class="admin-muted">({{ count($info['columns']) }} columns{{ $info['rows'] !== null ? ', '.$info['rows'].' rows' : '' }})</span></span>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Type</th>
                                <th>Nullable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($info['columns'] as $col)
                                <tr @if (in_array($col['name'], $info['missingColumns'])) class="bg-red-50 dark:bg-red-500/10" @endif>
                                    <td><code>{{ $col['name'] }}</code></td>
                                    <td><code>{{ $col['type'] }}</code></td>
                                    <td>{{ $col['nullable'] ? 'yes' : 'no' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

    {{-- All migration files --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>All migration files ({{ count($report['files']) }})</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Migration</th>
                        <th>Status</th>
                        <th>Batch</th>
                        <th>Creates</th>
                        <th>Alters</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['files'] as $file)
                        <tr>
                            <td><code>{{ $file['name'] }}</code></td>
                            <td>
                                @if ($file['ran'])
                                    <span class="admin-badge admin-badge-success">Ran</span>
                                @else
                                    <span class="admin-badge admin-badge-danger">Pending</span>
                                @endif
                            </td>
                            <td>{{ $file['batch'] ?? '—' }}</td>
                            <td><code>{{ implode(', ', $file['tablesCreated']) ?: '—' }}</code></td>
                            <td><code>{{ implode(', ', $file['tablesAltered']) ?: '—' }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($report['ranWithoutFile']) > 0)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm dark:border-zinc-800">
                <strong>Ran in DB but file missing:</strong>
                <code class="admin-code">{{ implode(', ', $report['ranWithoutFile']) }}</code>
            </div>
        @endif
        @if (count($report['extraTables']) > 0)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm dark:border-zinc-800">
                <strong>Extra tables in DB (no creating migration found):</strong>
                <code class="admin-code">{{ implode(', ', $report['extraTables']) }}</code>
            </div>
        @endif
    </div>
@endsection
