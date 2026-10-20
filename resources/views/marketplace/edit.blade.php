@extends('layouts.marketplace-layout')

@section('title', 'Editar '.$listing->title.' — Mercado')

@section('content')
<div class="mx-auto w-full max-w-3xl px-4 py-8">
    <nav class="mb-4 text-xs text-zinc-500" aria-label="Trilha de navegação">
        <a href="{{ route('marketplace.index') }}" class="hover:text-zinc-300">Mercado</a>
        <span aria-hidden="true"> / </span>
        <a href="{{ route('marketplace.show', ['listing' => $listing->slug]) }}" class="hover:text-zinc-300">{{ $listing->title }}</a>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-300">Editar</span>
    </nav>

    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Editar anúncio</h1>
            <p class="text-sm text-zinc-400">Status atual: <strong class="text-zinc-200">{{ $listing->statusLabel() }}</strong></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('marketplace.show', ['listing' => $listing->slug]) }}" class="mk-btn-outline">Ver anúncio</a>
            <a href="{{ route('marketplace.mine') }}" class="mk-btn-outline">Meus anúncios</a>
        </div>
    </header>

    @include('marketplace._form', ['listing' => $listing])
</div>
@endsection
