@extends('layouts.app')

@section('title', 'Recuperar senha')

@section('content')
<div class="flex min-h-[80vh] items-center justify-center px-4 py-10">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 shadow-xl backdrop-blur">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight">Recuperar senha</h1>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">E-mail</label>
                <input type="email" id="email" name="email" placeholder="Seu e-mail" value="{{ old('email') }}" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                Enviar link de recuperação
            </button>
        </form>
    </div>
</div>
@endsection
