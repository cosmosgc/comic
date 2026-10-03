@extends('layouts.admin-layout')

@section('title', 'Migrations')
@section('page-title', 'Migrations')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('migrate_output'))
        <div class="card mb-4">
            <div class="card-header">Last migrate output</div>
            <div class="card-body">
                <pre class="mb-0" style="white-space: pre-wrap;">{{ session('migrate_output') }}</pre>
            </div>
        </div>
    @endif

    {{-- Status overview --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Connection</h6>
                    <p class="card-text mb-0"><strong>{{ $report['connection'] }}</strong> ({{ $report['driver'] }})</p>
                    <small class="text-muted">{{ $report['database'] }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Migration files</h6>
                    <p class="card-text mb-0"><strong>{{ count($report['files']) }}</strong> total, {{ count($report['ran']) }} ran</p>
                    <small class="text-muted">{{ count($report['pending']) }} pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Tables</h6>
                    <p class="card-text mb-0"><strong>{{ count($report['actualTables']) }}</strong> in DB, {{ count($report['expectedTables']) }} expected</p>
                    <small class="text-muted">{{ count($report['missingTables']) }} missing, {{ count($report['extraTables']) }} extra</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Health</h6>
                    @if ($report['healthy'])
                        <span class="badge badge-success" style="font-size: 1rem;">Up to date</span>
                    @else
                        <span class="badge badge-danger" style="font-size: 1rem;">Needs attention</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Problems callout --}}
    @if (!$report['healthy'])
        <div class="alert alert-warning">
            <strong>Something is off:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($report['pending'] as $name)
                    <li>Pending migration: <code>{{ $name }}</code></li>
                @endforeach
                @foreach ($report['missingTables'] as $table)
                    <li>Missing table: <code>{{ $table }}</code> (expected by a migration, not in DB)</li>
                @endforeach
                @foreach ($report['ranWithoutFile'] as $name)
                    <li>Ran but file missing: <code>{{ $name }}</code> (DB says ran, no file on disk — was it deleted?)</li>
                @endforeach
                @foreach ($report['tables'] as $table => $info)
                    @if (!empty($info['missingColumns']))
                        <li>Table <code>{{ $table }}</code> missing columns: <code>{{ implode(', ', $info['missingColumns']) }}</code></li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Run migrations (no console needed) --}}
    <div class="card mb-4">
        <div class="card-header">Run migrations</div>
        <div class="card-body">
            @if (count($report['pending']) > 0)
                <p>Runs <code>php artisan migrate --force</code> on the server via this panel. No SSH needed.</p>
                <form method="POST" action="{{ route('admin.migrations.run') }}" onsubmit="return confirm('Run {{ count($report['pending']) }} pending migration(s)?')">
                    @csrf
                    <input type="hidden" name="action" value="migrate">
                    <button type="submit" class="btn btn-primary">Run {{ count($report['pending']) }} pending migration(s)</button>
                </form>
            @else
                <p class="mb-0 text-muted">Database is up to date — nothing to run.</p>
            @endif
        </div>
    </div>

    {{-- Pending migrations --}}
    <div class="card mb-4">
        <div class="card-header">Pending migrations ({{ count($report['pending']) }})</div>
        <div class="card-body p-0">
            @if (count($report['pending']) === 0)
                <p class="p-3 mb-0 text-muted">None — all files have run.</p>
            @else
                <table class="table table-striped mb-0">
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
            @endif
        </div>
    </div>

    {{-- Tables: expected vs actual --}}
    <div class="card mb-4">
        <div class="card-header">Tables: migrations vs database</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
                                    <span class="badge badge-danger">Missing in DB</span>
                                @elseif (!$info['expected'])
                                    <span class="badge badge-warning">Extra (no migration)</span>
                                @else
                                    <span class="badge badge-success">OK</span>
                                @endif
                            </td>
                            <td>{{ $info['rows'] ?? '—' }}</td>
                            <td>{{ count($info['columns']) }}</td>
                            <td>
                                @if (!empty($info['missingColumns']))
                                    <code class="text-danger">{{ implode(', ', $info['missingColumns']) }}</code>
                                @else
                                    <span class="text-muted">—</span>
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
            <div class="card mb-3">
                <div class="card-header">
                    <code>{{ $table }}</code>
                    <small class="text-muted">({{ count($info['columns']) }} columns{{ $info['rows'] !== null ? ', '.$info['rows'].' rows' : '' }})</small>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Type</th>
                                <th>Nullable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($info['columns'] as $col)
                                <tr @if (in_array($col['name'], $info['missingColumns'])) class="table-danger" @endif>
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
    <div class="card mb-4">
        <div class="card-header">All migration files ({{ count($report['files']) }})</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
                                    <span class="badge badge-success">Ran</span>
                                @else
                                    <span class="badge badge-danger">Pending</span>
                                @endif
                            </td>
                            <td>{{ $file['batch'] ?? '—' }}</td>
                            <td><code>{{ implode(', ', $file['tablesCreated']) ?: '—' }}</code></td>
                            <td><code>{{ implode(', ', $file['tablesAltered']) ?: '—' }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (count($report['ranWithoutFile']) > 0)
                <div class="p-3 border-top">
                    <strong>Ran in DB but file missing:</strong>
                    <code>{{ implode(', ', $report['ranWithoutFile']) }}</code>
                </div>
            @endif
            @if (count($report['extraTables']) > 0)
                <div class="p-3 border-top">
                    <strong>Extra tables in DB (no creating migration found):</strong>
                    <code>{{ implode(', ', $report['extraTables']) }}</code>
                </div>
            @endif
        </div>
    </div>
@endsection
