@extends('layouts.marketplace-layout')

@section('title', ($heading ?? 'Explorar').' — Mercado')

@section('content')
<div class="mx-auto w-full max-w-6xl px-4 py-8">

    <header class="mb-6 flex flex-col gap-2 border-b border-zinc-800 pb-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $heading ?? 'Explorar o mercado' }}</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ $listings->total() }} anúncio(s)
                @if ($searchQuery !== '')
                    para “{{ $searchQuery }}”
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('marketplace.browse') }}" class="mk-btn-ghost">Limpar filtros</a>
            @auth
                <a href="{{ route('marketplace.create') }}" class="mk-btn-primary">+ Vender</a>
            @endauth
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form method="GET" action="{{ route('marketplace.browse') }}" class="mk-panel space-y-4 p-4">
                <div>
                    <label for="filter-q" class="mk-label">Buscar</label>
                    <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="mk-input" placeholder="Título, tag, vendedor…">
                </div>

                <div>
                    <label for="filter-category" class="mk-label">Categoria</label>
                    <select id="filter-category" name="category" class="mk-input">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>
                                {{ $category->icon }} {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="filter-kind" class="mk-label">Tipo de item</label>
                        <select id="filter-kind" name="kind" class="mk-input">
                            <option value="">Todos</option>
                            @foreach (\App\Models\MarketplaceListing::kindOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['kind'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-type" class="mk-label">Oferta</label>
                        <select id="filter-type" name="type" class="mk-input">
                            <option value="">Todas</option>
                            @foreach (\App\Models\MarketplaceListing::typeOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="filter-condition" class="mk-label">Estado</label>
                        <select id="filter-condition" name="condition" class="mk-input">
                            <option value="">Todos</option>
                            @foreach (\App\Models\MarketplaceListing::conditionOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['condition'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-shipping" class="mk-label">Entrega</label>
                        <select id="filter-shipping" name="shipping" class="mk-input">
                            <option value="">Todas</option>
                            @foreach (\App\Models\MarketplaceListing::shippingOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['shipping'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <span class="mk-label">Preço (referência)</span>
                    <div class="flex items-center gap-2">
                        <input name="min_price" type="number" min="0" step="0.01" value="{{ $filters['min_price'] ?? '' }}" class="mk-input" placeholder="mín." aria-label="Preço mínimo">
                        <span class="text-zinc-500">–</span>
                        <input name="max_price" type="number" min="0" step="0.01" value="{{ $filters['max_price'] ?? '' }}" class="mk-input" placeholder="máx." aria-label="Preço máximo">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" name="has_comic" value="1" @checked(! empty($filters['has_comic'])) class="h-4 w-4 rounded border-zinc-700 bg-zinc-900 text-amber-500 focus:ring-amber-500">
                    Só com comic do site
                </label>

                <div>
                    <label for="filter-sort" class="mk-label">Ordenar por</label>
                    <select id="filter-sort" name="sort" class="mk-input">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Mais recentes</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Menor preço</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Maior preço</option>
                        <option value="watched" @selected(($filters['sort'] ?? '') === 'watched')>Mais favoritados</option>
                        <option value="ending" @selected(($filters['sort'] ?? '') === 'ending')>Terminando antes</option>
                    </select>
                </div>

                <button type="submit" class="mk-btn-primary w-full">Aplicar filtros</button>
            </form>
        </aside>

        <section>
            @if ($listings->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-800 p-10 text-center">
                    <p class="text-base font-semibold">Nada encontrado com esses filtros.</p>
                    <p class="mt-1 text-sm text-zinc-500">Tente remover algum filtro ou buscar por outro termo.</p>
                    <a href="{{ route('marketplace.browse') }}" class="mk-btn-outline mt-4">Ver todos os anúncios</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-marketplace-card :listing="$listing" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $listings->links() }}
                </div>
            @endif
        </section>
    </div>

</div>
@endsection
