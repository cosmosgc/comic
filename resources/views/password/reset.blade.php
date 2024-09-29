@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card p-4" style="width: 100%; max-width: 400px;">
        <h3 class="text-center mb-4">Reset Password</h3>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Email Address -->
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="{{ $email ?? old('email') }}" required>
            </div>

            <!-- Password -->
            <div class="form-group mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" required>
            </div>

            <!-- Confirm Password -->
            <div class="form-group mb-3">
                <label for="password-confirm" class="form-label">Confirm New Password</label>
                <input type="password" id="password-confirm" name="password_confirmation" class="form-control" placeholder="Confirm new password" required>
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            </div>
        </form>
    </div>
</div>
@endsection
