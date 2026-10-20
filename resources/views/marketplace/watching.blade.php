@extends('layouts.marketplace-layout')

@section('title', 'Favoritos — Mercado')

@section('content')
<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Favoritos</h1>
            <p class="text-sm text-zinc-400">Anúncios que você salvou para olhar depois.</p>
        </div>
        <a href="{{ route('marketplace.browse') }}" class="mk-btn-outline">Explorar mais</a>
    </header>

    @if ($listings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-800 p-10 text-center">
            <p class="text-lg font-semibold">Sua lista está vazia.</p>
            <p class="mt-1 text-sm text-zinc-400">Toque em “Favoritar” em qualquer anúncio para guardá-lo aqui.</p>
            <a href="{{ route('marketplace.browse') }}" class="mk-btn-primary mt-4">Ver anúncios</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($listings as $listing)
                <div class="space-y-1.5">
                    <x-marketplace-card :listing="$listing" />
                    <form method="POST" action="{{ route('marketplace.watch', ['listing' => $listing->slug]) }}" class="text-center">
                        @csrf
                        <button type="submit" class="text-xs text-zinc-500 hover:text-rose-300">Remover dos favoritos</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $listings->links() }}</div>
    @endif

</div>
@endsection
