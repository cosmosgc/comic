@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="container">
    <h1>Edit Profile</h1>

    <form action="{{ route('profile.update') }}" method="POST">
        @csrf

        <!-- Name Field -->
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" class="form-control @error('name') is-invalid @enderror"
                   id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Email Field -->
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror"
                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Password Field -->
        <div class="form-group">
            <label for="password">New Password (leave blank to keep current password)</label>
            <input type="password" class="form-control @error('password') is-invalid @enderror"
                   id="password" name="password">
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <!-- Password Confirmation Field -->
        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" class="form-control"
                   id="password_confirmation" name="password_confirmation">
        </div>

        <button type="submit" class="btn btn-primary">Update Profile</button>
        <a href="{{ route('profile.show') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection

@section('styles')
<style>
    /* Add any custom styles for the edit profile form here */
</style>
@endsection
