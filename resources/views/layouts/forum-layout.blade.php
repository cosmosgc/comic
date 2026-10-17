<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Fórum — '.config('app.site_title'))</title>

    <meta name="description" content="Fórum da comunidade: converse, tire dúvidas e compartilhe.">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen bg-zinc-950 text-zinc-100">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-violet-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
    Pular para o conteúdo
</a>

{{-- Detached-mode banner: make it obvious this is a separate area. --}}
<div class="border-b border-zinc-800 bg-gradient-to-r from-violet-600/25 via-indigo-600/10 to-transparent">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-1.5 text-xs">
        <span class="text-zinc-300">🧭 Você está no <strong class="text-violet-300">Fórum</strong> — um espaço à parte da comunidade.</span>
        <a href="{{ route('forum.exit') }}" class="font-semibold text-violet-300 transition hover:text-violet-200">Voltar ao site →</a>
    </div>
</div>

{{-- FORUM NAVBAR (distinct from the main site) --}}
<nav x-data="{ open: false }" class="sticky top-0 z-50 border-b border-zinc-800 bg-zinc-950/90 backdrop-blur" aria-label="Navegação do fórum">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-16 items-center justify-between gap-4">

            {{-- Forum brand --}}
            <a href="{{ route('forum.index') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 text-lg shadow-lg shadow-violet-900/40" aria-hidden="true">🗣️</span>
                <span class="flex flex-col leading-none">
                    <span class="text-base font-extrabold tracking-wide text-white">Fórum</span>
                    <span class="text-[10px] uppercase tracking-widest text-zinc-500">{{ config('app.site_title') }}</span>
                </span>
            </a>

            {{-- Desktop nav --}}
            <div class="hidden items-center gap-1 md:flex">
                <a href="{{ route('forum.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('forum.index') ? 'bg-violet-600/20 text-violet-200' : 'text-zinc-300 hover:bg-zinc-900 hover:text-white' }}">
                    Início
                </a>
                <a href="{{ route('forum.search') }}"
                   class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('forum.search') ? 'bg-violet-600/20 text-violet-200' : 'text-zinc-300 hover:bg-zinc-900 hover:text-white' }}">
                    Buscar
                </a>
                @auth
                    <a href="{{ route('forum.unread') }}"
                       class="rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('forum.unread') ? 'bg-violet-600/20 text-violet-200' : 'text-zinc-300 hover:bg-zinc-900 hover:text-white' }}">
                        Não lidos
                    </a>
                @endauth
            </div>

            {{-- Right cluster --}}
            <div class="hidden items-center gap-2 md:flex">
                <a href="{{ route('forum.exit') }}" class="btn-outline">← Voltar ao site</a>

                @auth
                    @include('components.notification-bell')

                    <div x-data="{ userOpen: false }" class="relative">
                        <button @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu"
                                class="flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm" aria-label="Menu da conta">
                            <span class="hidden max-w-28 truncate sm:inline">{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 opacity-70" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"/>
                            </svg>
                        </button>
                        <div x-show="userOpen" x-transition @click.outside="userOpen = false" x-cloak role="menu"
                             class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">
                            <a href="{{ route('profile.show') }}" role="menuitem" class="dropdown-link">Perfil</a>
                            @if (Auth::user()->admin_level >= 1)
                                <a href="{{ route('admin.forum.dashboard') }}" role="menuitem" class="dropdown-link">Admin do fórum</a>
                                <a href="{{ route('admin.dashboard') }}" role="menuitem" class="dropdown-link">Painel admin</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="dropdown-link">Sair</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="btn-outline">Cadastrar</a>
                @endauth
            </div>

            {{-- Mobile toggle --}}
            <button @click="open = !open" :aria-expanded="open.toString()" aria-controls="forum-mobile-menu" aria-label="Alternar menu"
                    class="rounded-lg p-2 hover:bg-zinc-800 md:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" id="forum-mobile-menu" x-cloak class="space-y-1 py-4 md:hidden">
            <a href="{{ route('forum.index') }}" class="mobile-link">Início</a>
            <a href="{{ route('forum.search') }}" class="mobile-link">Buscar</a>
            @auth
                <a href="{{ route('forum.unread') }}" class="mobile-link">Não lidos</a>
                <a href="{{ route('profile.show') }}" class="mobile-link">Perfil</a>
                @if (Auth::user()->admin_level >= 1)
                    <a href="{{ route('admin.forum.dashboard') }}" class="mobile-link">Admin do fórum</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mobile-link w-full text-left">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mobile-link">Entrar</a>
                <a href="{{ route('register') }}" class="mobile-link">Cadastrar</a>
            @endauth
            <a href="{{ route('forum.exit') }}" class="mobile-link text-violet-300">← Voltar ao site</a>
        </div>
    </div>
</nav>

{{-- PAGE CONTENT --}}
<main id="main-content">
    @yield('content')
</main>

<footer class="mt-12 border-t border-zinc-800 bg-zinc-950">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-zinc-500 sm:flex-row">
        <span class="flex items-center gap-2"><span aria-hidden="true">🗣️</span> Fórum · {{ config('app.site_title') }}</span>
        <nav class="flex items-center gap-5" aria-label="Rodapé do fórum">
            <a href="{{ route('forum.index') }}" class="transition hover:text-zinc-200">Início</a>
            <a href="{{ route('forum.search') }}" class="transition hover:text-zinc-200">Buscar</a>
            <a href="{{ route('forum.exit') }}" class="font-semibold text-violet-400 transition hover:text-violet-300">Voltar ao site</a>
        </nav>
    </div>
</footer>

@include('layouts.partials.interactions')

@yield('scripts')

</body>
</html>
