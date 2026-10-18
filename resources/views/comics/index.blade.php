@extends('layouts.app')

@section('title', 'Comics')

@section('meta')
    <meta name="description" content="Descubra os melhores comics da semana. Veja os mais populares e explore a coleção completa. Atualizações semanais e novos conteúdos sempre.">
    <meta name="keywords" content="comics, leitura online, comics populares, melhores comics, comics da semana">

    <meta property="og:title" content="Comics - Descubra os Melhores da Semana">
    <meta property="og:description" content="Veja os top comics da semana com os mais visualizados e explore a coleção completa.">
    <meta property="og:image" content="{{ asset('icon.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Comics - Descubra os Melhores da Semana">
    <meta name="twitter:description" content="Veja os comics mais populares da semana e explore a vasta coleção.">
    <meta name="twitter:image" content="{{ asset('icon.jpg') }}">
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    <!-- All comics -->
    <section>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">
                @if (($filter ?? null) === 'exclusivo')
                    🔒 Exclusivos
                @else
                    Comics
                @endif
            </h1>

            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 text-sm font-semibold" role="group" aria-label="Ordenar comics">
                    <a href="{{ route('comics.index') }}"
                       class="rounded-full px-3 py-1.5 transition
                              {{ ($sort ?? 'latest') !== 'liked' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                        Recentes
                    </a>
                    <a href="{{ route('comics.index', ['sort' => 'liked']) }}"
                       class="rounded-full px-3 py-1.5 transition
                              {{ ($sort ?? 'latest') === 'liked' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                        Mais curtidos
                    </a>
                    @auth
                        <a href="{{ route('comics.index', ['filter' => 'exclusivo']) }}"
                           class="rounded-full px-3 py-1.5 transition
                                  {{ ($filter ?? null) === 'exclusivo' ? 'bg-fuchsia-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                            🔒 Exclusivos
                        </a>
                    @endauth
                </div>

                <div class="flex items-center gap-1" role="group" aria-label="Layout dos comics">
                    <button type="button"
                            data-comics-layout="grid"
                            aria-pressed="{{ ($layout ?? 'grid') === 'grid' ? 'true' : 'false' }}"
                            title="Ver em grade"
                            class="rounded-lg p-2 transition {{ ($layout ?? 'grid') === 'grid' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 012.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                        </svg>
                    </button>
                    <button type="button"
                            data-comics-layout="list"
                            aria-pressed="{{ ($layout ?? 'grid') === 'list' ? 'true' : 'false' }}"
                            title="Ver em lista"
                            class="rounded-lg p-2 transition {{ ($layout ?? 'grid') === 'list' ? 'bg-indigo-600 text-white' : 'text-zinc-400 hover:text-zinc-100' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        @if (($filter ?? null) === 'exclusivo')
            <div class="mb-6 rounded-xl border border-fuchsia-500/30 bg-fuchsia-500/10 px-4 py-3 text-sm text-fuchsia-100">
                @auth
                    🔒 Conteúdos exclusivos para membros: quem não está logado vê o card bloqueado, sem abrir as páginas.
                @else
                    🔒 Estes comics são <strong>exclusivos para membros</strong>. Entre ou crie uma conta para ler:
                    <a href="{{ route('login') }}" class="font-semibold underline">entrar</a>
                    ·
                    <a href="{{ route('register') }}" class="font-semibold underline">criar conta</a>.
                @endauth
            </div>
        @endif

        @if (($layout ?? 'grid') === 'list')
            <div class="flex flex-col gap-4" data-comics-layout-root="list">
                @foreach ($comics as $comic)
                    <x-comic-list-item :comic="$comic" :liked-comic-ids="$likedComicIds ?? []" />
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:gap-6 md:grid-cols-3 lg:grid-cols-4" data-comics-layout-root="grid">
                @foreach ($comics as $comic)
                    <x-comic-card :comic="$comic" :liked-comic-ids="$likedComicIds ?? []" />
                @endforeach
            </div>
        @endif

        <!-- Pagination -->
        <div class="mt-8 flex justify-center">
            {{ $comics->links() }}
        </div>
    </section>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Grid/list preference: stored in a cookie (like the upload-mode
    // choice) so the server renders the chosen layout on every visit.
    document.querySelectorAll('[data-comics-layout]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var layout = btn.getAttribute('data-comics-layout');
            var d = new Date();
            d.setTime(d.getTime() + (365 * 24 * 60 * 60 * 1000));
            document.cookie = 'comics_layout=' + layout + ';expires=' + d.toUTCString() + ';path=/';
            window.location.reload();
        });
    });
});
</script>
@endsection
