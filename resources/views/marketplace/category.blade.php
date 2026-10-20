@extends('layouts.marketplace-layout')

@section('title', $category->name.' — Mercado')

@section('content')
<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <nav class="mb-4 text-xs text-zinc-500" aria-label="Trilha de navegação">
        <a href="{{ route('marketplace.index') }}" class="hover:text-zinc-300">Mercado</a>
        <span aria-hidden="true"> / </span>
        <a href="{{ route('marketplace.browse') }}" class="hover:text-zinc-300">Explorar</a>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-300">{{ $category->name }}</span>
    </nav>

    <header class="mb-6 rounded-2xl border border-zinc-800 bg-gradient-to-br from-zinc-900 to-zinc-950 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-3xl" aria-hidden="true">
                    {{ $category->icon ?: '🏷️' }}
                </span>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">{{ $category->name }}</h1>
                    @if ($category->description)
                        <p class="mt-1 max-w-2xl text-sm text-zinc-400">{{ $category->description }}</p>
                    @endif
                    <p class="mt-2 text-xs text-zinc-500">{{ $listings->total() }} anúncio(s) ativo(s)</p>
                </div>
            </div>
            @auth
                <a href="{{ route('marketplace.create') }}" class="mk-btn-primary shrink-0">+ Vender aqui</a>
            @endauth
        </div>

        @if ($children->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-800 pt-4">
                @foreach ($children as $child)
                    <a href="{{ $child->url() }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">
                        {{ $child->icon }} {{ $child->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    @if ($listings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-800 p-10 text-center">
            <p class="text-lg font-semibold">Nenhum anúncio nesta categoria ainda.</p>
            <p class="mt-1 text-sm text-zinc-400">Que tal ser a primeira pessoa a anunciar?</p>
            @auth
                <a href="{{ route('marketplace.create') }}" class="mk-btn-primary mt-4">Criar anúncio</a>
            @endauth
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($listings as $listing)
                <x-marketplace-card :listing="$listing" />
            @endforeach
        </div>
        <div class="mt-6">{{ $listings->links() }}</div>
    @endif

</div>
@endsection
