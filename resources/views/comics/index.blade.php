@extends('layouts.app')

@section('title', 'Comics Index')

@section('meta')
    <meta name="description" content="Descubra os melhores comics da semana. Veja os comics mais populares e explore nossa coleção completa. Atualizações semanais e novos conteúdos sempre.">
    <meta name="keywords" content="comics, quadrinhos, leitura online, comics populares, melhores comics, comics da semana">

    <meta property="og:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta property="og:description" content="Veja os top comics da semana com os quadrinhos mais visualizados e explore nossa coleção completa.">
    <meta property="og:image" content="{{ asset('icon.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta name="twitter:description" content="Veja os quadrinhos mais populares da semana e explore nossa vasta coleção.">
    <meta name="twitter:image" content="{{ asset('icon.jpg') }}">
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    <!-- Top comics: swipeable rail on phones, grid on desktop -->
    <section class="mb-10">
        <h2 class="mb-4 text-xl font-semibold">
            Top comics da semana
        </h2>

        <ul class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 md:grid md:grid-cols-4 md:gap-6 md:overflow-visible md:pb-0 lg:grid-cols-4">
            @foreach ($topComics as $comic)
                <li class="relative w-40 shrink-0 snap-start sm:w-48 md:w-auto">
                    <span class="absolute right-2 top-2 z-10 rounded-full bg-black/70 px-2 py-1 text-xs font-semibold text-white">
                        👁 {{ $comic->view_count }}
                    </span>

                    <x-comic-card :comic="$comic" :minified="true" :liked-comic-ids="$likedComicIds ?? []" />
                </li>
            @endforeach
        </ul>
    </section>

    <!-- All comics -->
    <section>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">
                Comics
            </h1>

            <div class="flex items-center gap-1 text-sm font-semibold">
                <a href="{{ route('comics.index') }}"
                   class="rounded-full px-3 py-1.5 transition
                          {{ ($sort ?? 'latest') !== 'liked' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                    Latest
                </a>
                <a href="{{ route('comics.index', ['sort' => 'liked']) }}"
                   class="rounded-full px-3 py-1.5 transition
                          {{ ($sort ?? 'latest') === 'liked' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                    Most liked
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-6 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($comics as $comic)
                <x-comic-card :comic="$comic" :liked-comic-ids="$likedComicIds ?? []" />
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8 flex justify-center">
            {{ $comics->links() }}
        </div>
    </section>

</div>
@endsection
