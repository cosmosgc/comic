@extends('layouts.admin-layout')

@section('title', 'Changelog manager')
@section('page-title', 'Changelog')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif

    <p class="admin-muted mb-6">
        One file per PR in <code class="admin-code">changelogs/*.json</code>, shown publicly at
        <a href="{{ route('changelog.index') }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400">/changelog</a>.
    </p>

    {{-- PRs missing an entry --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Pull requests without a changelog entry ({{ $repo }})</span>
        </div>
        @if ($fetchError)
            <p class="px-5 py-4 text-sm text-red-700 dark:text-red-300">{{ $fetchError }}</p>
        @elseif (count($missing) === 0)
            <p class="admin-muted px-5 py-4">Everything is documented — no missing PRs in the recent list.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
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
                                    <a href="{{ $pr['url'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline dark:text-indigo-400">#{{ $pr['number'] }}</a>
                                </td>
                                <td>
                                    {{ $pr['title'] }}
                                    <br><span class="admin-muted text-xs">by {{ $pr['author'] }} · {{ $pr['created_at'] }}</span>
                                </td>
                                <td>
                                    @if ($pr['merged'])
                                        <span class="admin-badge admin-badge-success">merged</span>
                                    @else
                                        <span class="admin-badge admin-badge-info">{{ $pr['state'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.changelogs.import') }}" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="number" value="{{ $pr['number'] }}">
                                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Import &amp; edit</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Existing entries --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Entries ({{ count($entries) }})</span>
            <a href="{{ route('admin.changelogs.create') }}" class="admin-btn admin-btn-success admin-btn-sm">New entry</a>
        </div>
        @if (count($entries) === 0)
            <p class="admin-muted px-5 py-4">No entries yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
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
                                    <a href="{{ route('changelog.show', $entry['id']) }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $entry['title'] }}</a>
                                </td>
                                <td><span class="admin-badge admin-badge-secondary">{{ $entry['category'] }}</span></td>
                                <td>{{ $entry['pr'] ? '#'.$entry['pr'] : '—' }}</td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.changelogs.edit', $entry['id']) }}" class="admin-btn admin-btn-warning admin-btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.changelogs.destroy', $entry['id']) }}"
                                          class="inline-block" onsubmit="return confirm('Delete this entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
