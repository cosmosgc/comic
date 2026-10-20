@extends('layouts.marketplace-layout')

@section('title', 'Meus anúncios — Mercado')

@php
    $statusColors = [
        'draft' => 'border border-zinc-700 bg-zinc-800 text-zinc-300',
        'active' => 'border border-emerald-900 bg-emerald-950 text-emerald-300',
        'reserved' => 'border border-sky-900 bg-sky-950 text-sky-300',
        'sold' => 'border border-rose-900 bg-rose-950 text-rose-300',
        'expired' => 'border border-amber-900 bg-amber-950 text-amber-300',
        'hidden' => 'border border-zinc-800 bg-zinc-900 text-zinc-400',
    ];
    $tabs = [
        'all' => 'Todos',
        'active' => 'Ativos',
        'draft' => 'Rascunhos',
        'reserved' => 'Reservados',
        'sold' => 'Vendidos',
        'expired' => 'Expirados',
        'hidden' => 'Ocultos',
    ];
@endphp

@section('content')
<div class="mx-auto w-full max-w-5xl px-4 py-8">

    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Meus anúncios</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $activeCount }} de {{ $limit }} anúncios ativos/rascunhos em uso.
                @if ($activeCount >= $limit)
                    <span class="text-amber-300">Limite atingido — finalize ou remova um anúncio para criar outro.</span>
                @endif
            </p>
        </div>
        @if ($activeCount < $limit)
            <a href="{{ route('marketplace.create') }}" class="mk-btn-primary shrink-0">+ Novo anúncio</a>
        @endif
    </header>

    <div class="mb-6 flex flex-wrap gap-x-5 gap-y-1 border-b border-zinc-800">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('marketplace.mine', $key === 'all' ? [] : ['status' => $key]) }}"
               class="border-b-2 px-0.5 pb-2.5 text-[11px] font-semibold uppercase tracking-wide transition-colors {{ $status === $key ? 'border-amber-400 text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-300' }}">
                {{ $label }}
                <span class="opacity-60">({{ $counts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </div>

    @if ($listings->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-800 p-10 text-center">
            <p class="text-base font-semibold">Nenhum anúncio aqui.</p>
            <p class="mt-1 text-sm text-zinc-500">
                @if ($status === 'all')
                    Crie seu primeiro anúncio e ele aparece nesta lista.
                @else
                    Não há anúncios com este status.
                @endif
            </p>
            <a href="{{ route('marketplace.create') }}" class="mk-btn-primary mt-4">Criar anúncio</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($listings as $listing)
                <article class="mk-panel p-3 transition-colors hover:border-zinc-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a href="{{ $listing->url() }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-md border border-zinc-800 bg-zinc-950">
                            @if ($listing->coverUrl())
                                <img src="{{ $listing->coverUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <span class="flex h-full w-full items-center justify-center text-2xl opacity-50" aria-hidden="true">🏷️</span>
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ $listing->url() }}" class="truncate font-semibold transition-colors hover:text-amber-300">{{ $listing->title }}</a>
                                <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusColors[$listing->status] ?? 'border border-zinc-700 bg-zinc-800 text-zinc-300' }}">
                                    {{ $listing->statusLabel() }}
                                </span>
                                @unless ($listing->is_approved)
                                    <span class="rounded border border-amber-900 bg-amber-950 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-300">Em análise</span>
                                @endunless
                            </div>
                            <p class="mt-1 text-sm text-zinc-500">
                                <span class="font-semibold text-amber-300">{{ $listing->priceLabel() }}</span>
                                · {{ $listing->category?->name }}
                                @if ($listing->published_at)
                                    · {{ $listing->published_at->format('d/m/Y') }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ $listing->view_count }} visita(s) · {{ $listing->watch_count }} favorito(s) · {{ $listing->inquiry_count }} contato(s)
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-1.5">
                            <a href="{{ $listing->url() }}" class="mk-btn-ghost">Ver</a>
                            <a href="{{ route('marketplace.edit', ['listing' => $listing->slug]) }}" class="mk-btn-ghost">Editar</a>

                            @if (in_array($listing->status, ['draft', 'hidden', 'expired'], true))
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $listing->status === 'expired' ? 'renew' : 'active' }}">
                                    <button type="submit" class="mk-btn-ghost border-emerald-600/50 text-emerald-300">
                                        {{ $listing->status === 'expired' ? 'Renovar' : 'Publicar' }}
                                    </button>
                                </form>
                            @endif

                            @if ($listing->status === 'active')
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reserve">
                                    <button type="submit" class="mk-btn-ghost">Reservar</button>
                                </form>
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="bump">
                                    <button type="submit" class="mk-btn-ghost">Impulsionar</button>
                                </form>
                            @endif

                            @unless ($listing->status === 'sold')
                                <form method="POST" action="{{ route('marketplace.status', ['listing' => $listing->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="sold">
                                    <button type="submit" class="mk-btn-ghost">Vendido</button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('marketplace.destroy', ['listing' => $listing->slug]) }}"
                                  onsubmit="return confirm('Remover este anúncio? As fotos também serão apagadas.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mk-btn-ghost border-rose-600/50 text-rose-300">Excluir</button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $listings->links() }}</div>
    @endif

</div>
@endsection
