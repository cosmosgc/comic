@extends('layouts.admin-layout')

@section('title', 'Changelog manager')
@section('page-title', 'Changelog')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <p class="text-muted">
        One file per PR in <code>changelogs/*.json</code>, shown publicly at
        <a href="{{ route('changelog.index') }}" target="_blank">/changelog</a>.
    </p>

    {{-- PRs missing an entry --}}
    <div class="card mb-4">
        <div class="card-header">Pull requests without a changelog entry ({{ $repo }})</div>
        <div class="card-body p-0">
            @if ($fetchError)
                <p class="p-3 mb-0 text-danger">{{ $fetchError }}</p>
            @elseif (count($missing) === 0)
                <p class="p-3 mb-0 text-muted">Everything is documented — no missing PRs in the recent list.</p>
            @else
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>PR</th>
                            <th>Title</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missing as $pr)
                            <tr>
                                <td>
                                    <a href="{{ $pr['url'] }}" target="_blank" rel="noopener">#{{ $pr['number'] }}</a>
                                </td>
                                <td>
                                    {{ $pr['title'] }}
                                    <br><small class="text-muted">by {{ $pr['author'] }} · {{ $pr['created_at'] }}</small>
                                </td>
                                <td>
                                    @if ($pr['merged'])
                                        <span class="badge badge-success">merged</span>
                                    @else
                                        <span class="badge badge-info">{{ $pr['state'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.changelogs.import') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="number" value="{{ $pr['number'] }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Import &amp; edit</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Existing entries --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Entries ({{ count($entries) }})</span>
            <a href="{{ route('admin.changelogs.create') }}" class="btn btn-sm btn-success">New entry</a>
        </div>
        <div class="card-body p-0">
            @if (count($entries) === 0)
                <p class="p-3 mb-0 text-muted">No entries yet.</p>
            @else
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>PR</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td><code>{{ $entry['date'] }}</code></td>
                                <td>
                                    <a href="{{ route('changelog.show', $entry['id']) }}" target="_blank">{{ $entry['title'] }}</a>
                                </td>
                                <td><span class="badge badge-secondary">{{ $entry['category'] }}</span></td>
                                <td>{{ $entry['pr'] ? '#'.$entry['pr'] : '—' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('admin.changelogs.edit', $entry['id']) }}" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="POST" action="{{ route('admin.changelogs.destroy', $entry['id']) }}"
                                          class="d-inline" onsubmit="return confirm('Delete this entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
