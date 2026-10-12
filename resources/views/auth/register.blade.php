@extends('layouts.app')

@section('title', 'Cadastrar')

@section('content')
<div class="flex min-h-[80vh] items-center justify-center px-4 py-10">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 shadow-xl backdrop-blur">
        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight">Criar conta</h1>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-zinc-300">Nome</label>
                <input type="text" id="name" name="name" placeholder="Seu nome" value="{{ old('name') }}" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('name')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">E-mail</label>
                <input type="email" id="email" name="email" placeholder="Seu e-mail" value="{{ old('email') }}" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('email')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">Senha</label>
                <input type="password" id="password" name="password" placeholder="Sua senha" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('password')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-zinc-300">Confirmar senha</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repita a senha" required
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                Cadastrar
            </button>
        </form>

        <p class="pt-4 text-center text-sm text-zinc-400">
            Já tem conta?
            <a href="{{ route('login') }}" class="font-medium text-indigo-400 hover:text-indigo-300">Entrar</a>
        </p>
    </div>
</div>
@endsection
