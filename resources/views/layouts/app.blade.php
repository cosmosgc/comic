<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', config('app.site_title'))</title>

    <meta name="description" content="Descubra os melhores comics da semana. Veja os mais populares e explore a coleção completa.">
    <meta name="keywords" content="comics, leitura online">

    {{-- Open Graph --}}
    <meta property="og:title" content="Comics Index">
    <meta property="og:description" content="Top comics da semana">
    <meta property="og:image" content="{{ Vite::asset('resources/images/icon.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

</head>

<body>

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
    Pular para o conteúdo
</a>

<!-- NAVBAR -->
<nav x-data="{ open: false }" class="sticky top-0 z-50 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur" aria-label="Navegação principal">
    <div class="w-full px-4">
        <div class="flex h-16 items-center justify-between">

            <!-- Logo -->
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-wide text-white">
                {{ config('app.site_title') }}
            </a>

            <!-- Desktop Nav (from lg: below that the row does not fit) -->
            <div class="hidden items-center gap-4 lg:flex xl:gap-6">

                <!-- Search -->
                <div class="relative w-56 xl:w-72">
                    <label for="comic-search" class="sr-only">Buscar comics</label>
                    <input
                        id="comic-search"
                        type="text"
                        role="combobox"
                        aria-expanded="false"
                        aria-controls="comic-search-results"
                        aria-autocomplete="list"
                        data-search-page="{{ route('comics.search') }}"
                        placeholder="Buscar comics..."
                        class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        autocomplete="off"
                    >
                    <ul id="comic-search-results"
                        role="listbox"
                        aria-label="Resultados da busca"
                        class="absolute mt-2 hidden w-full overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">
                    </ul>
                </div>

                @auth
                    @include('components.notification-bell')

                    <!-- Nav menus: one hamburger per section, like the profile dropdown -->
                    <x-nav-dropdown label="Explorar" id="nav-explore">
                        <a href="{{ route('comics.index') }}" role="menuitem" class="dropdown-link">Comics</a>
                        <a href="{{ route('leaderboard.index') }}" role="menuitem" class="dropdown-link">Ranking</a>
                        <a href="{{ route('changelog.index') }}" role="menuitem" class="dropdown-link">Novidades</a>
                    </x-nav-dropdown>

                    <x-nav-dropdown label="Comunidade" id="nav-community">
                        <a href="{{ route('posts.index') }}" role="menuitem" class="dropdown-link">Comunidade</a>
                        <a href="{{ route('forum.index') }}" role="menuitem" class="dropdown-link">Fórum</a>
                    </x-nav-dropdown>

                    <a href="{{ route('comics.create') }}" class="btn-primary">Publicar</a>

                    <!-- User Dropdown -->
                    <div x-data="{ userOpen: false }" class="relative">
                        <button
                            type="button"
                            @click="userOpen = !userOpen"
                            :aria-expanded="userOpen.toString()"
                            aria-haspopup="menu"
                            class="flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm"
                            aria-label="Menu da conta">

                            <span class="hidden max-w-28 truncate xl:inline">{{ Auth::user()->name }}</span>

                            <svg class="h-4 w-4 opacity-70" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"/>
                            </svg>
                        </button>

                        <div
                            x-show="userOpen"
                            x-transition
                            @click.outside="userOpen = false"
                            x-cloak
                            role="menu"
                            class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">

                            <a href="{{ route('profile.show') }}" role="menuitem" class="dropdown-link">
                                Perfil
                            </a>
                            <a href="{{ route('bookmarks.index') }}" role="menuitem" class="dropdown-link">
                                Salvos
                            </a>
                            @if (Auth::user()->admin_level >= 1)
                                <a href="{{ route('admin.dashboard') }}" role="menuitem" class="dropdown-link">
                                    Admin
                                </a>
                            @endif

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="dropdown-link">
                                    Sair
                                </button>
                            </form>
                        </div>
                    </div>

                @else
                    <!-- Nav menus for guests: one hamburger per section -->
                    <x-nav-dropdown label="Explorar" id="nav-explore">
                        <a href="{{ route('comics.index') }}" role="menuitem" class="dropdown-link">Comics</a>
                        <a href="{{ route('leaderboard.index') }}" role="menuitem" class="dropdown-link">Ranking</a>
                        <a href="{{ route('changelog.index') }}" role="menuitem" class="dropdown-link">Novidades</a>
                    </x-nav-dropdown>

                    <x-nav-dropdown label="Comunidade" id="nav-community">
                        <a href="{{ route('posts.index') }}" role="menuitem" class="dropdown-link">Comunidade</a>
                        <a href="{{ route('forum.index') }}" role="menuitem" class="dropdown-link">Fórum</a>
                    </x-nav-dropdown>

                    <a href="{{ route('login') }}" class="btn-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="btn-outline">Cadastrar</a>
                @endauth
            </div>

            <!-- Mobile actions -->
            <div class="flex items-center gap-2 lg:hidden">
                @auth
                    <a href="{{ route('comics.create') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Publicar
                    </a>
                @endauth
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-menu" aria-label="Alternar menu" class="rounded-lg p-2 hover:bg-zinc-800">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open" id="mobile-menu" x-cloak class="space-y-1 py-4 lg:hidden">
            <div class="relative px-1 pb-2">
                <label for="comic-search-mobile" class="sr-only">Buscar comics</label>
                <input
                    id="comic-search-mobile"
                    type="text"
                    placeholder="Buscar comics..."
                    data-search-page="{{ route('comics.search') }}"
                    class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    autocomplete="off"
                >
            </div>
            <p class="nav-menu-label">Explorar</p>
            <a href="{{ route('comics.index') }}" class="mobile-link">Comics</a>
            <a href="{{ route('leaderboard.index') }}" class="mobile-link">Ranking</a>
            @auth
                <a href="{{ route('bookmarks.index') }}" class="mobile-link">Salvos</a>
            @endauth
            <p class="nav-menu-label">Comunidade</p>
            <a href="{{ route('posts.index') }}" class="mobile-link">Comunidade</a>
            <a href="{{ route('forum.index') }}" class="mobile-link">Fórum</a>
            <a href="{{ route('changelog.index') }}" class="mobile-link">Novidades</a>
            @auth
                <p class="nav-menu-label">Conta</p>
                @if (Auth::user()->admin_level >= 1)
                    <a href="{{ route('admin.dashboard') }}" class="mobile-link">Admin</a>
                @endif
                <a href="{{ route('profile.show') }}" class="mobile-link">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mobile-link w-full text-left">Sair</button>
                </form>
            @else
                <p class="nav-menu-label">Conta</p>
                <a href="{{ route('login') }}" class="mobile-link">Entrar</a>
                <a href="{{ route('register') }}" class="mobile-link">Cadastrar</a>
            @endauth
        </div>
    </div>
</nav>

<!-- PAGE LAYOUT -->
{{-- Full-bleed shell: the side panels sit against the viewport edges (the
     `p-1` on each aside is the only outer gap) and <main> carries the page
     gutter instead, so the whole width of the screen is used. `min-w-0` on
     <main> keeps wide content (grids, tables, code blocks) from stretching
     the column and scrolling the whole page. --}}
<div class="flex w-full items-start gap-4 py-6">

    @if($showPanels ?? false)
        <aside class="sticky top-20 hidden max-h-[calc(100vh-6rem)] w-64 shrink-0 overflow-y-auto rounded-xl bg-zinc-900/50 p-1 panel-scroll lg:block"
               aria-label="Painel lateral">
            @include('components.left-panel')
        </aside>
    @endif

    <main id="main-content" class="min-w-0 flex-1 px-4 lg:pl-0 xl:pr-0">
        @yield('content')
    </main>

    @if($showPanels ?? false)
        <aside class="sticky top-20 hidden max-h-[calc(100vh-6rem)] w-64 shrink-0 overflow-y-auto rounded-xl bg-zinc-900/50 p-1 panel-scroll xl:block"
               aria-label="Painel lateral">
            @include('components.right-panel')
        </aside>
    @endif

</div>

<footer class="mt-12 border-t border-zinc-800">
    <div class="flex w-full flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-zinc-500 sm:flex-row">
        <span>{{ config('app.site_title') }}</span>
        <nav class="flex items-center gap-5" aria-label="Rodapé">
            <a href="{{ route('comics.index') }}" class="transition hover:text-zinc-200">Comics</a>
            <a href="{{ route('posts.index') }}" class="transition hover:text-zinc-200">Comunidade</a>
            <a href="{{ route('changelog.index') }}" class="transition hover:text-zinc-200">Novidades</a>
        </nav>
    </div>
</footer>

<!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get the necessary data for analytics
            const analyticsData = {
                url: window.location.href,
                ip_address: '{{ request()->ip() }}',
                user_agent: navigator.userAgent,
                event_type: 'page_view',
                device_type: /Mobi|Android/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
                referral_source: document.referrer || 'Direct',
                campaign: '',
                duration: 0,
                browser: getBrowser(),
                os: getOS(),
                user_id: '{{ Auth::id() }}' // Pass the user ID from the server
            };

            // Function to get browser name
            function getBrowser() {
                if (navigator.userAgent.indexOf("Chrome") > -1) {
                    return "Chrome";
                } else if (navigator.userAgent.indexOf("Firefox") > -1) {
                    return "Firefox";
                } else if (navigator.userAgent.indexOf("Safari") > -1) {
                    return "Safari";
                } else if (navigator.userAgent.indexOf("Edge") > -1) {
                    return "Edge";
                } else {
                    return "Other";
                }
            }

            // Function to get OS name
            function getOS() {
                if (navigator.userAgent.indexOf("Win") > -1) {
                    return "Windows";
                } else if (navigator.userAgent.indexOf("Mac") > -1) {
                    return "MacOS";
                } else if (navigator.userAgent.indexOf("X11") > -1 || navigator.userAgent.indexOf("Linux") > -1) {
                    return "Linux";
                } else if (/Android/i.test(navigator.userAgent)) {
                    return "Android";
                } else if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    return "iOS";
                } else {
                    return "Other";
                }
            }

            // Send the analytics data via API
            fetch("{{ route('analytics') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Include CSRF token for security
                },
                body: JSON.stringify(analyticsData),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Analytics data stored:', data);
            })
            .catch(error => {
                console.error('Error storing analytics data:', error);
            });
        });

    </script>

    <script>
    const BASE_URL = "{{ url('') }}";
     const SEARCH_URL = "{{ route('api.comics') }}";
    document.addEventListener('DOMContentLoaded', function () {
        const desktopInput = document.getElementById('comic-search');
        const mobileInput = document.getElementById('comic-search-mobile');
        const resultsBox = document.getElementById('comic-search-results');
        let timeout = null;
        let activeIdx = -1;

        function setExpanded(open) {
            if (desktopInput) desktopInput.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function hideResults() {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
            activeIdx = -1;
            setExpanded(false);
        }

        function highlight() {
            resultsBox.querySelectorAll('li').forEach(function (li, i) {
                li.classList.toggle('is-active', i === activeIdx);
                li.setAttribute('aria-selected', i === activeIdx ? 'true' : 'false');
            });
        }

        function goTo(url) {
            window.location.href = url;
        }

        function renderResults(data) {
            resultsBox.innerHTML = '';
            activeIdx = -1;
            if (data.length === 0) {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                li.className = 'cursor-default px-3 py-2 text-sm text-zinc-500';
                li.textContent = 'Nenhum comic encontrado';
                resultsBox.appendChild(li);
            } else {
                data.forEach(function (comic) {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', 'false');
                    li.dataset.url = BASE_URL + '/comics/' + comic.slug;
                    const title = document.createElement('span');
                    title.className = 'font-medium text-zinc-100';
                    title.textContent = comic.title;
                    const author = document.createElement('span');
                    author.className = 'text-zinc-400';
                    author.textContent = ' · ' + comic.author;
                    li.appendChild(title);
                    li.appendChild(author);
                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        goTo(li.dataset.url);
                    });
                    resultsBox.appendChild(li);
                });
            }
            resultsBox.style.display = 'block';
            setExpanded(true);
        }

        function search(query) {
            clearTimeout(timeout);
            if (query.length < 2) {
                hideResults();
                return;
            }
            timeout = setTimeout(function () {
                fetch(SEARCH_URL + '?search=' + encodeURIComponent(query) + '&limit=5', {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(renderResults)
                    .catch(hideResults);
            }, 250);
        }

        function bindInput(input) {
            if (!input) return;
            input.addEventListener('input', function () {
                // Mobile field has no dropdown: only track the query for Enter.
                if (input.dataset.searchPage !== undefined && input.id === 'comic-search-mobile') return;
                search(this.value.trim());
            });
            input.addEventListener('keydown', function (e) {
                const items = resultsBox.querySelectorAll('li[data-url]');
                if (e.key === 'ArrowDown' && items.length) {
                    e.preventDefault();
                    activeIdx = (activeIdx + 1) % items.length;
                    highlight();
                } else if (e.key === 'ArrowUp' && items.length) {
                    e.preventDefault();
                    activeIdx = (activeIdx - 1 + items.length) % items.length;
                    highlight();
                } else if (e.key === 'Enter') {
                    if (activeIdx >= 0 && items[activeIdx]) {
                        e.preventDefault();
                        goTo(items[activeIdx].dataset.url);
                    } else {
                        // No suggestion picked (or mobile field): full search page.
                        const q = this.value.trim();
                        const page = this.dataset.searchPage;
                        if (q.length >= 2 && page) {
                            e.preventDefault();
                            goTo(page + '?search=' + encodeURIComponent(q));
                        }
                    }
                } else if (e.key === 'Escape') {
                    hideResults();
                }
            });
        }

        bindInput(desktopInput);
        bindInput(mobileInput);

        // Close the dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!resultsBox.contains(e.target) && e.target !== desktopInput) {
                hideResults();
            }
        });
    });
    </script>


    {{-- Shared front-end interactions (hover cards, follow toggle, XP toast). --}}
    @include('layouts.partials.interactions')

    @stack('scripts')

</body>
</html>
