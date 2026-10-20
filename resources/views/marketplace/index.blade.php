@extends('layouts.marketplace-layout')

@section('title', 'Mercado — '.config('app.site_title'))

@section('content')
<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <section class="relative overflow-hidden rounded-2xl border border-amber-500/20 bg-gradient-to-br from-amber-500/15 via-orange-500/5 to-transparent p-6 sm:p-8">
        <div class="max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-300">Classificados da comunidade</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Compre, venda e troque com quem também é daqui.</h1>
            <p class="mt-3 text-sm leading-relaxed text-zinc-300">
                Comics do site, figures, prints, arte e serviços. O preço é só uma referência:
                a conversa e o combinado acontecem direto entre as pessoas. O site não processa pagamentos.
            </p>

            <form method="GET" action="{{ route('marketplace.browse') }}" class="mt-5 flex flex-col gap-2 sm:flex-row">
                <label for="marketplace-q" class="sr-only">Buscar no mercado</label>
                <input id="marketplace-q" name="q" type="search" class="mk-input min-w-0 flex-1" placeholder="Buscar por título, tag, vendedor…">
                <label for="marketplace-category" class="sr-only">Categoria</label>
                <select id="marketplace-category" name="category" class="mk-input sm:w-52">
                    <option value="">Todas as categorias</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="mk-btn-primary shrink-0">Buscar</button>
            </form>

            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                <span class="text-zinc-400">Atalhos:</span>
                <a href="{{ route('marketplace.browse', ['type' => 'wanted']) }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">Procura-se</a>
                <a href="{{ route('marketplace.browse', ['kind' => 'comic']) }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">Comics</a>
                <a href="{{ route('marketplace.browse', ['kind' => 'physical']) }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">Itens físicos</a>
                <a href="{{ route('marketplace.browse', ['type' => 'giveaway']) }}" class="mk-chip hover:border-amber-500/60 hover:text-amber-200">Doações</a>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 border-t border-zinc-800/80 pt-5 text-center sm:max-w-md">
            <div>
                <p class="text-2xl font-extrabold text-amber-300">{{ number_format($stats['active']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-zinc-400">Anúncios ativos</p>
            </div>
            <div>
                <p class="text-2xl font-extrabold text-amber-300">{{ number_format($stats['sellers']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-zinc-400">Vendedores</p>
            </div>
            <div>
                <p class="text-2xl font-extrabold text-amber-300">{{ number_format($stats['categories']) }}</p>
                <p class="text-[11px] uppercase tracking-wide text-zinc-400">Categorias</p>
            </div>
        </div>
    </section>

    @auth
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-900/40 px-4 py-3 text-sm">
            <span class="text-zinc-300">Tem algo parado em casa? Anuncie em poucos minutos.</span>
            <a href="{{ route('marketplace.create') }}" class="mk-btn-primary">+ Vender algo</a>
        </div>
    @endauth

    <section class="mt-8">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold">Categorias</h2>
                <p class="text-sm text-zinc-400">Vá direto ao que procura.</p>
            </div>
            <a href="{{ route('marketplace.browse') }}" class="text-sm font-semibold text-amber-300 hover:text-amber-200">Ver tudo →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @forelse ($categories as $category)
                <a href="{{ $category->url() }}" class="mk-card flex flex-col items-center gap-1 p-4 text-center">
                    <span class="text-2xl" aria-hidden="true">{{ $category->icon ?: '🏷️' }}</span>
                    <span class="text-sm font-semibold leading-tight">{{ $category->name }}</span>
                    <span class="text-[11px] text-zinc-500">{{ $category->listings_count }} anúncio(s)</span>
                </a>
            @empty
                <p class="col-span-full text-sm text-zinc-500">Nenhuma categoria criada ainda.</p>
            @endforelse
        </div>
    </section>

    @if ($featured->isNotEmpty())
        <section class="mt-10">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-extrabold">Destaques</h2>
                    <p class="text-sm text-zinc-400">Escolhidos pela moderação.</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($featured as $listing)
                    <x-marketplace-card :listing="$listing" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold">Recém-chegados</h2>
                <p class="text-sm text-zinc-400">Os anúncios mais recentes do mercado.</p>
            </div>
            <a href="{{ route('marketplace.browse') }}" class="text-sm font-semibold text-amber-300 hover:text-amber-200">Explorar →</a>
        </div>

        @if ($latest->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-800 p-10 text-center">
                <p class="text-lg font-semibold">Ainda não há anúncios por aqui.</p>
                <p class="mt-1 text-sm text-zinc-400">Seja a primeira pessoa a colocar algo no mercado.</p>
                @auth
                    <a href="{{ route('marketplace.create') }}" class="mk-btn-primary mt-4">Criar o primeiro anúncio</a>
                @else
                    <a href="{{ route('login') }}" class="mk-btn-primary mt-4">Entrar para anunciar</a>
                @endauth
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($latest as $listing)
                    <x-marketplace-card :listing="$listing" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($endingSoon->isNotEmpty() || $wanted->isNotEmpty())
        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            @if ($endingSoon->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-xl font-extrabold">Terminando em breve</h2>
                    <div class="space-y-2">
                        @foreach ($endingSoon as $listing)
                            <a href="{{ $listing->url() }}" class="mk-card flex items-center gap-3 p-3">
                                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-zinc-800">
                                    @if ($listing->coverUrl())
                                        <img src="{{ $listing->coverUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ $listing->title }}</p>
                                    <p class="text-xs text-zinc-400">Expira {{ $listing->expires_at?->diffForHumans() }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-bold text-amber-300">{{ $listing->priceShort() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($wanted->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-xl font-extrabold">Procurando</h2>
                    <div class="space-y-2">
                        @foreach ($wanted as $listing)
                            <a href="{{ $listing->url() }}" class="mk-card flex items-center gap-3 p-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-zinc-800 text-xl" aria-hidden="true">🔎</div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ $listing->title }}</p>
                                    <p class="text-xs text-zinc-400">{{ $listing->locationLabel() ?: 'Sem local informado' }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-bold text-amber-300">{{ $listing->priceShort() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif

    <div class="mt-10 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-zinc-300">
        <p class="font-semibold text-amber-200">Segurança primeiro</p>
        <p class="mt-1">{{ config('marketplace.safety_note') }}</p>
    </div>

</div>
@endsection
