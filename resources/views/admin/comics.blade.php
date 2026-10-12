@extends('layouts.admin-layout')

@section('title', 'Comics')

@section('page-title', 'Comics')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>All Comics</span>
        </div>
        <div class="admin-table-wrap">
            <table id="comicsTable" class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Artist</th>
                        <th>Slug</th>
                        <th>Created At</th>
                        <th>Views</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comics as $comic)
                        <tr>
                            <td>{{ $comic->id }}</td>
                            <td class="font-medium">{{ $comic->title }}</td>
                            <td>{{ $comic->author }}</td>
                            <td><code>{{ $comic->slug }}</code></td>
                            <td class="whitespace-nowrap">{{ $comic->created_at }}</td>
                            <td>{{ $comic->view_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#comicsTable').DataTable({
                "order": [[4, "desc"]] // Order by Created At (5th column, index 4) in descending order
            });
        });
    </script>
@endsection
