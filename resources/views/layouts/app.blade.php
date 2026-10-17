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
    <meta property="og:image" content="{{ asset('icon.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

</head>

<body>

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
    Pular para o conteúdo
</a>

<!-- NAVBAR -->
<nav x-data="{ open: false }" class="sticky top-0 z-50 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur" aria-label="Navegação principal">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-16 items-center justify-between">

            <!-- Logo -->
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-wide text-white">
                {{ config('app.site_title') }}
            </a>

            <!-- Desktop Nav -->
            <div class="hidden items-center gap-6 md:flex">

                <!-- Search -->
                <div class="relative w-72">
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

                <a href="{{ route('posts.index') }}" class="nav-link">Comunidade</a>
                <a href="{{ route('forum.index') }}" class="nav-link">Fórum</a>
                <a href="{{ route('comics.index') }}" class="nav-link">Comics</a>
                <a href="{{ route('changelog.index') }}" class="nav-link">Novidades</a>
                <a href="{{ route('leaderboard.index') }}" class="nav-link">Ranking</a>

                @auth
                    <a href="{{ route('bookmarks.index') }}" class="nav-link">Salvos</a>
                    <a href="{{ route('comics.create') }}" class="nav-link">Publicar</a>
                    @if (Auth::user()->admin_level >= 1)
                        <a href="{{ route('admin.dashboard') }}" class="nav-link">Admin</a>
                    @endif

                    @include('components.notification-bell')

                    <!-- User Dropdown -->
                    <div x-data="{ userOpen: false }" class="relative">
                        <button
                            @click="userOpen = !userOpen"
                            :aria-expanded="userOpen.toString()"
                            aria-haspopup="menu"
                            class="flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm"
                            aria-label="Menu da conta">

                            <span class="hidden max-w-28 truncate sm:inline">{{ Auth::user()->name }}</span>

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
                    <a href="{{ route('login') }}" class="btn-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="btn-outline">Cadastrar</a>
                @endauth
            </div>

            <!-- Mobile Menu Button -->
            <button @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-menu" aria-label="Alternar menu" class="rounded-lg p-2 hover:bg-zinc-800 md:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open" id="mobile-menu" x-cloak class="space-y-1 py-4 md:hidden">
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
            <a href="{{ route('posts.index') }}" class="mobile-link">Comunidade</a>
            <a href="{{ route('forum.index') }}" class="mobile-link">Fórum</a>
            <a href="{{ route('comics.index') }}" class="mobile-link">Comics</a>
            <a href="{{ route('changelog.index') }}" class="mobile-link">Novidades</a>
            <a href="{{ route('leaderboard.index') }}" class="mobile-link">Ranking</a>
            @auth
                <a href="{{ route('bookmarks.index') }}" class="mobile-link">Salvos</a>
                <a href="{{ route('comics.create') }}" class="mobile-link">Publicar</a>
                @if (Auth::user()->admin_level >= 1)
                    <a href="{{ route('admin.dashboard') }}" class="mobile-link">Admin</a>
                @endif
                <a href="{{ route('profile.show') }}" class="mobile-link">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mobile-link w-full text-left">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mobile-link">Entrar</a>
                <a href="{{ route('register') }}" class="mobile-link">Cadastrar</a>
            @endauth
        </div>
    </div>
</nav>

<!-- PAGE LAYOUT -->
<div class="mx-auto">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-12">

        @if(isset($showPanels) && $showPanels)
            <aside class="md:col-span-2" aria-label="Sidebar left">
                @include('components.left-panel')
            </aside>
        @endif

        <main id="main-content" class="{{ isset($showPanels) && $showPanels ? 'md:col-span-8' : 'md:col-span-12' }}">
            @yield('content')
        </main>

        @if(isset($showPanels) && $showPanels)
            <aside class="md:col-span-2" aria-label="Sidebar right">
                @include('components.right-panel')
            </aside>
        @endif

    </div>
</div>

<footer class="mt-12 border-t border-zinc-800">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-zinc-500 sm:flex-row">
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


    <script>
    const HOVERCARD_API = "{{ url('api/users') }}";
    (function () {
        const cache = new Map();
        let card = null;
        let showTimer = null;
        let hideTimer = null;
        let currentName = null;

        function ensureCard() {
            if (card) return card;
            card = document.createElement('div');
            card.id = 'profile-hovercard';
            card.className = 'fixed z-[90] hidden w-72 overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-2xl';
            card.addEventListener('mouseenter', function () {
                clearTimeout(hideTimer);
            });
            card.addEventListener('mouseleave', scheduleHide);
            document.body.appendChild(card);
            return card;
        }

        function esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(data) {
            const el = ensureCard();
            const banner = data.banner_url
                ? '<img src="' + esc(data.banner_url) + '" alt="" class="absolute inset-0 h-full w-full object-cover">'
                : '';
            el.innerHTML =
                '<div class="relative h-20 bg-gradient-to-r from-indigo-900 via-zinc-900 to-zinc-900">' + banner + '</div>' +
                '<div class="px-3 pb-3">' +
                    '<img src="' + esc(data.avatar_url) + '" alt="" class="relative z-10 -mt-7 h-14 w-14 rounded-full border-4 border-zinc-900 object-cover">' +
                    '<a href="' + esc(data.profile_url) + '" class="mt-1 block truncate font-bold text-zinc-100 hover:underline">' + esc(data.name) + '</a>' +
                    (data.bio ? '<p class="mt-0.5 line-clamp-2 text-xs text-zinc-400">' + esc(data.bio) + '</p>' : '') +
                    '<p class="mt-1 text-xs">' +
                        (data.level ? '<span class="rounded-full bg-indigo-500/20 px-1.5 py-0.5 font-bold text-indigo-300">Nv ' + data.level + '</span>' : '') +
                        (data.badge ? ' <span title="' + esc(data.badge.label) + '">' + esc(data.badge.icon) + ' ' + esc(data.badge.label) + '</span>' : '') +
                    '</p>' +
                    '<p class="mt-2 text-xs text-zinc-500">' +
                        '<span class="font-semibold text-zinc-300">' + (data.comics_count ?? 0) + '</span> Comics · ' +
                        '<span class="font-semibold text-zinc-300">' + (data.posts_count ?? 0) + '</span> Posts' +
                    '</p>' +
                    (data.follows_enabled && !data.is_self && data.follow_url
                        ? '<button type="button" data-style="chip" data-following="' + (data.following ? '1' : '0') + '" ' +
                          'onclick="toggleFollow(\'' + esc(data.follow_url) + '\', this)" ' +
                          'class="mt-2 inline-flex items-center rounded-lg px-3 py-1 text-xs font-semibold transition ' +
                          (data.following
                              ? 'border border-zinc-700 text-zinc-200 hover:border-rose-700 hover:text-rose-300'
                              : 'bg-indigo-600 text-white hover:bg-indigo-500') + '">' +
                          (data.following ? 'Seguindo' : 'Seguir') + '</button>'
                        : '') +
                '</div>';
        }

        function place(anchor) {
            const el = ensureCard();
            const rect = anchor.getBoundingClientRect();
            const W = 288, H = el.offsetHeight || 220, GAP = 8;
            let left = Math.min(Math.max(8, rect.left), window.innerWidth - W - 8);
            let top = rect.bottom + GAP;
            if (top + H > window.innerHeight - 8) {
                top = Math.max(8, rect.top - H - GAP);
            }
            el.style.left = left + 'px';
            el.style.top = top + 'px';
        }

        function show(anchor) {
            const name = anchor.getAttribute('data-hovercard');
            if (!name) return;
            currentName = name;
            const render2 = function (data) {
                if (currentName !== name) return;
                render(data);
                const el = ensureCard();
                el.classList.remove('hidden');
                place(anchor);
            };
            if (cache.has(name)) {
                render2(cache.get(name));
                return;
            }
            fetch(HOVERCARD_API + '/' + encodeURIComponent(name) + '/card', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    cache.set(name, data);
                    render2(data);
                })
                .catch(function () {});
        }

        function scheduleHide() {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                if (card) card.classList.add('hidden');
                currentName = null;
            }, 200);
        }

        document.addEventListener('mouseover', function (e) {
            const anchor = e.target.closest ? e.target.closest('[data-hovercard]') : null;
            if (!anchor) return;
            clearTimeout(hideTimer);
            clearTimeout(showTimer);
            const name = anchor.getAttribute('data-hovercard');
            showTimer = setTimeout(function () { show(anchor); }, name && cache.has(name) ? 120 : 350);
        });

        document.addEventListener('mouseout', function (e) {
            const anchor = e.target.closest ? e.target.closest('[data-hovercard]') : null;
            if (!anchor) return;
            clearTimeout(showTimer);
            if (card && !card.matches(':hover')) scheduleHide();
        });

        document.addEventListener('scroll', function () {
            if (card) card.classList.add('hidden');
        }, { passive: true, capture: true });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && card) card.classList.add('hidden');
        });
    })();
    </script>

    {{-- AJAX XP pop: controllers include the award in JSON responses. --}}
    <script>
        window.openXpToast = function (payload) {
            if (!payload) return;
            var hasExp = (payload.exp || 0) > 0;
            if (!hasExp && !payload.leveled_up && !(payload.badges && payload.badges.length)) return;

            var box = document.createElement('div');
            box.className = 'pointer-events-none fixed bottom-5 right-5 z-[96] w-72 max-w-[calc(100vw-2rem)]';
            box.style.transition = 'opacity .3s ease, transform .3s ease';
            box.style.opacity = '0';
            box.style.transform = 'translateY(1rem)';

            var html = '<div style="border-radius:1rem;border:1px solid rgba(99,102,241,.4);'
                + 'background:rgba(24,24,27,.97);padding:1rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);'
                + 'color:#e4e4e7;font-family:sans-serif;">';
            if (hasExp) {
                html += '<div style="display:flex;align-items:center;gap:.5rem;">'
                    + '<span style="font-size:1.5rem;">⚡</span><div>'
                    + '<p style="margin:0;font-weight:700;color:#a5b4fc;">+' + payload.exp + ' XP</p>'
                    + '<p style="margin:0;font-size:.75rem;color:#a1a1aa;">Experiência ganha!</p></div></div>';
            }
            if (payload.leveled_up) {
                html += '<div style="margin-top:.5rem;border-radius:.5rem;background:rgba(79,70,229,.2);padding:.5rem .75rem;">'
                    + '<p style="margin:0;font-size:.85rem;font-weight:600;color:#c7d2fe;">🎉 Subiu para o nível '
                    + payload.level + '!</p></div>';
            }
            (payload.badges || []).forEach(function (b) {
                html += '<div style="margin-top:.5rem;border-radius:.5rem;background:rgba(245,158,11,.15);padding:.5rem .75rem;">'
                    + '<p style="margin:0;font-size:.75rem;font-weight:600;color:#fde68a;">'
                    + (b.icon || '🏅') + ' Conquista: ' + (b.label || '') + '</p></div>';
            });
            html += '</div>';
            box.innerHTML = html;
            document.body.appendChild(box);

            requestAnimationFrame(function () {
                box.style.opacity = '1';
                box.style.transform = 'translateY(0)';
            });
            setTimeout(function () {
                box.style.opacity = '0';
                box.style.transform = 'translateY(.5rem)';
                setTimeout(function () { box.remove(); }, 300);
            }, 5200);
        };
    </script>

@yield('scripts')

{{-- Gamification XP pop (flashed by App\Services\Gamification). --}}
@include('components.xp-toast')

    {{-- Follow toggle: used by profiles, the hover card and user lists. --}}
    <script>
        window.toggleFollow = function (url, btn) {
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
                if (!data) return;
                var on = !!data.following;
                btn.dataset.following = on ? '1' : '0';
                if (btn.dataset.style === 'chip') {
                    btn.textContent = on ? 'Seguindo' : 'Seguir';
                    btn.classList.toggle('bg-indigo-600', !on);
                    btn.classList.toggle('text-white', !on);
                    btn.classList.toggle('border', on);
                    btn.classList.toggle('border-zinc-700', on);
                    btn.classList.toggle('text-zinc-200', on);
                }
                document.querySelectorAll('[data-followers-count]').forEach(function (el) {
                    el.textContent = data.followers;
                });
            }).catch(function () {});
        };
    </script>

</body>
</html>
