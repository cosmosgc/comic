@extends('layouts.admin-layout')

@section('title', 'Edit User')

@section('page-title', 'Edit User')

@section('content')
    <div class="admin-card max-w-2xl">
        <div class="admin-card-header">
            <span>Edit User: {{ $user->name }}</span>
        </div>
        <div class="admin-card-body">
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="admin-label">Name</label>
                    <input type="text" name="name" id="name" class="admin-input" value="{{ old('name', $user->name) }}" required>
                </div>

                <div>
                    <label for="email" class="admin-label">Email</label>
                    <input type="email" name="email" id="email" class="admin-input" value="{{ old('email', $user->email) }}" required>
                </div>

                <div>
                    <label for="password" class="admin-label">Password <span class="admin-muted">(leave blank if not changing)</span></label>
                    <input type="password" name="password" id="password" class="admin-input">
                </div>

                <div>
                    <label for="password_confirmation" class="admin-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="admin-input">
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="admin-btn admin-btn-primary">Update User</button>
                    <a href="{{ route('admin.users.index') }}" class="admin-btn admin-btn-secondary">Back to Users List</a>
                </div>
            </form>
        </div>
    </div>
@endsection
