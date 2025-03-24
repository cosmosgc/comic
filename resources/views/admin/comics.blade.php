@extends('layouts.admin-layout')

@section('title', 'Comics')

@section('page-title', 'Comics Management')

@section('content')
    <h2>All Comics</h2>
    <table id="comicsTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>artist</th>
                <th>slug</th>
                <th>Created At</th>
                <th>views</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comics as $comic)
                <tr>
                    <td>{{ $comic->id }}</td>
                    <td>{{ $comic->title }}</td>
                    <td>{{ $comic->author }}</td>
                    <td>{{ $comic->slug }}</td>
                    <td>{{ $comic->created_at }}</td>
                    <td>{{ $comic->view_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
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
