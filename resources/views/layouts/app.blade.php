<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', config('app.site_title'))</title>

    <meta name="description" content="Descubra os melhores comics da semana. Veja os comics mais populares e explore nossa coleção completa.">
    <meta name="keywords" content="comics, quadrinhos, leitura online">

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

<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">

<!-- NAVBAR -->
<nav x-data="{ open: false }" class="sticky top-0 z-50 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-16 items-center justify-between">

            <!-- Logo -->
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-wide text-white">
                {{ config('app.site_title') }}
            </a>

            <!-- Desktop Nav -->
            <div class="hidden md:flex items-center gap-6">

                <!-- Search -->
                <div class="relative w-72">
                    <input
                        id="comic-search"
                        type="text"
                        placeholder="Search comics..."
                        class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        autocomplete="off"
                    >
                    <ul id="comic-search-results"
                        class="absolute mt-2 hidden w-full overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">
                    </ul>
                </div>

                <a href="{{ route('posts.index') }}" class="nav-link">Social</a>
                <a href="{{ route('comics.index') }}" class="nav-link">Comics</a>

                @auth
                    <a href="{{ route('comics.create') }}" class="nav-link">Upload</a>

                    <!-- User Dropdown -->
                    <div x-data="{ userOpen: false }" class="relative">
                        <button
                            @click="userOpen = !userOpen"
                            class="flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm">

                            {{ Auth::user()->name }}

                            <svg class="h-4 w-4 opacity-70" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"/>
                            </svg>
                        </button>

                        <div
                            x-show="userOpen"
                            x-transition
                            @click.outside="userOpen = false"
                            x-cloak
                            class="absolute right-0 mt-2 w-48 rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">

                            <a href="{{ route('profile.show') }}" class="dropdown-link">
                                Profile
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-link w-full text-left">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>

                @else
                    <a href="{{ route('login') }}" class="btn-primary">Login</a>
                    <a href="{{ route('register') }}" class="btn-outline">Register</a>
                @endauth
            </div>

            <!-- Mobile Menu Button -->
            <button @click="open = !open" class="md:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open" class="md:hidden py-4 space-y-3">
            <a href="{{ route('posts.index') }}" class="mobile-link">Social</a>
            <a href="{{ route('comics.index') }}" class="mobile-link">Comics</a>
            @auth
                <a href="{{ route('comics.create') }}" class="mobile-link">Upload</a>
                <a href="{{ route('profile.show') }}" class="mobile-link">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="mobile-link w-full text-left">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mobile-link">Login</a>
                <a href="{{ route('register') }}" class="mobile-link">Register</a>
            @endauth
        </div>
    </div>
</nav>

<!-- PAGE LAYOUT -->
<div class="mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

        @if(isset($showPanels) && $showPanels)
            <aside class="md:col-span-2">
                @include('components.left-panel')
            </aside>
        @endif

        <main class="{{ isset($showPanels) && $showPanels ? 'md:col-span-8' : 'md:col-span-12' }}">
            @yield('content')
        </main>

        @if(isset($showPanels) && $showPanels)
            <aside class="md:col-span-2">
                @include('components.right-panel')
            </aside>
        @endif

    </div>
</div>
<!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
     const SEARCH_URL = "{{ route('comics.search') }}";
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('comic-search');
        const resultsBox = document.getElementById('comic-search-results');
        let timeout = null;

        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            const query = this.value.trim();
            if (query.length < 2) {
                resultsBox.style.display = 'none';
                return;
            }

            // Add a small debounce to avoid hammering the server
            timeout = setTimeout(() => {
                fetch(`{{ route('api.comics') }}?search=${encodeURIComponent(query)}&limit=5`)
                    .then(response => response.json())
                    .then(data => {
                        resultsBox.innerHTML = '';
                        if (data.length === 0) {
                            resultsBox.style.display = 'none';
                            return;
                        }
                        data.forEach(comic => {
                            const li = document.createElement('li');
                            li.classList.add('list-group-item', 'list-group-item-action');
                            li.textContent = `${comic.title} (${comic.author})`;
                            li.addEventListener('click', () => {
                                window.location.href = `${BASE_URL}/comics/${comic.slug}`;
                            });
                            resultsBox.appendChild(li);
                        });
                        resultsBox.style.display = 'block';
                    });
            }, 300); // 300ms debounce
        });

        // Close the dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!resultsBox.contains(e.target) && e.target !== searchInput) {
                resultsBox.style.display = 'none';
            }
        });
    });
    </script>


@yield('scripts')

</body>
</html>
