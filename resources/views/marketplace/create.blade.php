@extends('layouts.marketplace-layout')

@section('title', 'Novo anúncio — Mercado')

@section('content')
<div class="mx-auto w-full max-w-3xl px-4 py-8">
    <nav class="mb-4 text-xs text-zinc-500" aria-label="Trilha de navegação">
        <a href="{{ route('marketplace.index') }}" class="hover:text-zinc-300">Mercado</a>
        <span aria-hidden="true"> / </span>
        <span class="text-zinc-300">Novo anúncio</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Criar anúncio</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Leva poucos minutos. Você pode salvar como rascunho e publicar depois.
        </p>
    </header>

    @include('marketplace._form', ['listing' => null])
</div>
@endsection
