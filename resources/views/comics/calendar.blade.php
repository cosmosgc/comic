@extends('layouts.app')

@section('title', 'Calendário de lançamentos')

@section('content')
    <section class="mx-auto w-full max-w-4xl">
        <div class="mb-6 rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-300">Em breve</p>
            <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">Calendário de lançamentos</h1>
            <p class="mt-2 text-sm text-zinc-300">
                Veja os próximos comics. Lançamentos mais distantes aparecem sem detalhes para evitar spoilers.
            </p>
        </div>

        @forelse ($scheduledComics as $comic)
            @php
                $isFarAhead = $comic->isFarAhead();
                $canRead = $comic->isVisibleTo(auth()->user());
            @endphp

            <article class="mb-3 flex gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow-sm sm:p-4">
                @if ($isFarAhead)
                    <div class="relative shrink-0" aria-label="Comic futuro sem detalhes">
                        <img src="{{ asset('storage/'.$comic->image_path) }}"
                             alt="Capa de um comic futuro"
                             loading="lazy"
                             class="aspect-[3/4] h-28 w-20 rounded-lg object-cover blur-sm sm:h-32 sm:w-24">
                        <span class="absolute inset-0 flex items-center justify-center rounded-lg bg-zinc-950/45">
                            <svg class="h-6 w-6 text-zinc-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-7a2 2 0 00-2-2H6a2 2 0 00-2 2v7a2 2 0 002 2zm10-11V7a4 4 0 00-8 0v3h8z" />
                            </svg>
                        </span>
                    </div>
                @elseif ($canRead)
                    <a href="{{ route('comics.showBySlug', $comic->slug) }}" class="shrink-0">
                        <img src="{{ asset('storage/'.$comic->image_path) }}"
                             alt="Capa de {{ $comic->title }}"
                             loading="lazy"
                             class="aspect-[3/4] h-28 w-20 rounded-lg object-cover transition hover:brightness-110 sm:h-32 sm:w-24">
                    </a>
                @else
                    <div class="shrink-0">
                        <img src="{{ asset('storage/'.$comic->image_path) }}"
                             alt="Capa de {{ $comic->title }}"
                             loading="lazy"
                             class="aspect-[3/4] h-28 w-20 rounded-lg object-cover sm:h-32 sm:w-24">
                    </div>
                @endif

                <div class="min-w-0 flex-1 self-center">
                    <p class="text-sm font-medium text-amber-300">
                        {{ $comic->published_at->format('d/m/Y · H:i') }}
                    </p>

                    @if ($isFarAhead)
                        <h2 class="mt-1 text-lg font-semibold text-zinc-200">Comic em breve</h2>
                        <p class="mt-1 text-sm text-zinc-400">Os detalhes serão revelados mais perto do lançamento.</p>
                    @elseif ($canRead)
                        <a href="{{ route('comics.showBySlug', $comic->slug) }}" class="group">
                            <h2 class="mt-1 truncate text-lg font-semibold text-white transition group-hover:text-indigo-300">{{ $comic->title }}</h2>
                        </a>
                        <p class="mt-1 text-sm text-zinc-400">Por {{ $comic->author }}</p>
                    @else
                        <h2 class="mt-1 truncate text-lg font-semibold text-white">{{ $comic->title }}</h2>
                        <p class="mt-1 text-sm text-zinc-400">Por {{ $comic->author }}</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-700 bg-zinc-900 p-10 text-center">
                <p class="text-lg font-semibold text-zinc-200">Nenhum lançamento agendado</p>
                <p class="mt-2 text-sm text-zinc-500">Quando houver novos comics a caminho, eles aparecerão aqui.</p>
            </div>
        @endforelse

        @if ($scheduledComics->hasPages())
            <div class="mt-6">
                {{ $scheduledComics->links() }}
            </div>
        @endif
    </section>
@endsection
