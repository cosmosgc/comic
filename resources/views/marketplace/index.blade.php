@extends('layouts.marketplace-layout')

@section('title', 'Mercado — '.config('app.site_title'))

@section('content')
<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <section class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 sm:p-8">
        <div class="max-w-3xl">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-400">Classificados da comunidade</p>
            <h1 class="mt-3 text-3xl font-semibold leading-[1.08] tracking-[-0.02em] sm:text-4xl">Compre, venda e troque com quem também é daqui.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-zinc-400">
                Comics do site, figures, prints, arte e serviços. O preço é só uma referência:
                a conversa e o combinado acontecem direto entre as pessoas. O site não processa pagamentos.
            </p>

            <form method="GET" action="{{ route('marketplace.browse') }}" class="mt-6 flex flex-col gap-2 sm:flex-row">
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
                <span class="text-zinc-500">Atalhos:</span>
                <a href="{{ route('marketplace.browse', ['type' => 'wanted']) }}" class="mk-chip">Procura-se</a>
                <a href="{{ route('marketplace.browse', ['kind' => 'comic']) }}" class="mk-chip">Comics</a>
                <a href="{{ route('marketplace.browse', ['kind' => 'physical']) }}" class="mk-chip">Itens físicos</a>
                <a href="{{ route('marketplace.browse', ['type' => 'giveaway']) }}" class="mk-chip">Doações</a>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-3 divide-x divide-zinc-800 border-t border-zinc-800 pt-5 text-center sm:max-w-md">
            <div class="px-2">
                <p class="text-2xl font-semibold tracking-tight text-zinc-100">{{ number_format($stats['active']) }}</p>
                <p class="mt-1 text-[10px] font-medium uppercase tracking-[0.12em] text-zinc-500">Anúncios ativos</p>
            </div>
            <div class="px-2">
                <p class="text-2xl font-semibold tracking-tight text-zinc-100">{{ number_format($stats['sellers']) }}</p>
                <p class="mt-1 text-[10px] font-medium uppercase tracking-[0.12em] text-zinc-500">Vendedores</p>
            </div>
            <div class="px-2">
                <p class="text-2xl font-semibold tracking-tight text-zinc-100">{{ number_format($stats['categories']) }}</p>
                <p class="mt-1 text-[10px] font-medium uppercase tracking-[0.12em] text-zinc-500">Categorias</p>
            </div>
        </div>
    </section>

    @auth
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm">
            <span class="text-zinc-400">Tem algo parado em casa? Anuncie em poucos minutos.</span>
            <a href="{{ route('marketplace.create') }}" class="mk-btn-primary">+ Vender algo</a>
        </div>
    @endauth

    <section class="mt-8">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Categorias</h2>
                <p class="mt-1 text-sm text-zinc-500">Vá direto ao que procura.</p>
            </div>
            <a href="{{ route('marketplace.browse') }}" class="text-sm font-medium text-zinc-400 transition-colors hover:text-amber-300">Ver tudo →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @forelse ($categories as $category)
                <a href="{{ $category->url() }}" class="mk-card flex items-center gap-3 p-3 text-left">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-950 text-lg" aria-hidden="true">{{ $category->icon ?: '🏷️' }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold leading-tight">{{ $category->name }}</span>
                        <span class="mt-1 block text-[11px] text-zinc-500">{{ $category->listings_count }} anúncio(s)</span>
                    </span>
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
                    <h2 class="text-lg font-semibold tracking-tight">Destaques</h2>
                    <p class="mt-1 text-sm text-zinc-500">Escolhidos pela moderação.</p>
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
                <h2 class="text-lg font-semibold tracking-tight">Recém-chegados</h2>
                <p class="mt-1 text-sm text-zinc-500">Os anúncios mais recentes do mercado.</p>
            </div>
            <a href="{{ route('marketplace.browse') }}" class="text-sm font-medium text-zinc-400 transition-colors hover:text-amber-300">Explorar →</a>
        </div>

        @if ($latest->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-800 p-10 text-center">
                <p class="text-base font-semibold">Ainda não há anúncios por aqui.</p>
                <p class="mt-1 text-sm text-zinc-500">Seja a primeira pessoa a colocar algo no mercado.</p>
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
                    <h2 class="mb-3 text-lg font-semibold tracking-tight">Terminando em breve</h2>
                    <div class="space-y-2">
                        @foreach ($endingSoon as $listing)
                            <a href="{{ $listing->url() }}" class="mk-card flex items-center gap-3 p-3">
                                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-zinc-800 bg-zinc-950">
                                    @if ($listing->coverUrl())
                                        <img src="{{ $listing->coverUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ $listing->title }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">Expira {{ $listing->expires_at?->diffForHumans() }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-amber-300">{{ $listing->priceShort() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($wanted->isNotEmpty())
                <section>
                    <h2 class="mb-3 text-lg font-semibold tracking-tight">Procurando</h2>
                    <div class="space-y-2">
                        @foreach ($wanted as $listing)
                            <a href="{{ $listing->url() }}" class="mk-card flex items-center gap-3 p-3">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-zinc-800 bg-zinc-950 text-xl" aria-hidden="true">🔎</div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ $listing->title }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $listing->locationLabel() ?: 'Sem local informado' }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-amber-300">{{ $listing->priceShort() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif

    <div class="mt-10 rounded-lg border border-zinc-800 border-l-2 border-l-amber-400 bg-zinc-900 p-5">
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-400">Segurança primeiro</p>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-400">{{ config('marketplace.safety_note') }}</p>
    </div>

</div>
@endsection
