@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
<div class="flex min-h-[80vh] items-center justify-center px-4 py-10">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 shadow-xl backdrop-blur">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight">Reset Password</h1>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="{{ $email ?? old('email') }}" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">New Password</label>
                <input type="password" id="password" name="password" placeholder="Enter new password" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password-confirm" class="mb-1 block text-sm font-medium text-zinc-300">Confirm New Password</label>
                <input type="password" id="password-confirm" name="password_confirmation" placeholder="Confirm new password" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                Reset Password
            </button>
        </form>
    </div>
</div>
@endsection
