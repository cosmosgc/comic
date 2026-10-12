@extends('layouts.admin-layout')

@section('title', 'Users')

@section('page-title', 'Users')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>All Users</span>
        </div>
        <div class="admin-table-wrap">
            <table id="usersTable" class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td class="font-medium">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role }}</td>
                            <td class="whitespace-nowrap">{{ $user->created_at }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="admin-btn admin-btn-primary admin-btn-sm">Edit</a>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this user?')">
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
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable();
        });
    </script>
@endsection
