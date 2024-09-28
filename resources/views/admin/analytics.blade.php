@extends('layouts.admin-layout')

@section('title', 'Analytics')

@section('page-title', 'Analytics Dashboard')

@section('content')
    <h2>Recent Activities</h2>
    <table id="analyticsTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>User</th>
                <th>IP Address</th>
                <th>URL</th>
                <th>Event Type</th>
                <th>Agent</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($analytics as $entry)
                <tr>
                    <td>{{ optional($entry->user)->name ?? 'Guest' }}</td>
                    <td>{{ $entry->ip_address }}</td>
                    <td>{{ $entry->url }}</td>
                    <td>{{ $entry->event_type }}</td>
                    <td>{{ $entry->user_agent }}</td>
                    <td>{{ $entry->created_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Pagination links -->
    {{ $analytics->links() }}
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#analyticsTable').DataTable();
        });
    </script>
@endsection
