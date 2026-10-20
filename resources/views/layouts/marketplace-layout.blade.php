<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Mercado — '.config('app.site_title'))</title>

    <meta name="description" content="Mercado da comunidade: anuncie, troque e encontre comics, figures, arte e serviços.">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#09090b">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="marketplace min-h-screen bg-zinc-950 text-zinc-100">

<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-md focus:bg-amber-400 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-zinc-950">
    Pular para o conteúdo
</a>

{{-- Detached-mode banner: make it obvious this is a separate area. --}}
<div class="border-b border-zinc-800 bg-zinc-900">
    <div class="flex w-full flex-wrap items-center justify-between gap-2 px-4 py-2 text-[11px]">
        <span class="flex items-center gap-2 text-zinc-400">
            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400" aria-hidden="true"></span>
            Você está no <strong class="font-semibold text-zinc-100">Mercado</strong> — anúncios da comunidade. Combine tudo pelo chat; o site não processa pagamentos.
        </span>
        <a href="{{ route('marketplace.exit') }}" class="font-semibold text-amber-300 transition-colors hover:text-amber-200">Voltar ao site →</a>
    </div>
</div>

{{-- MARKETPLACE NAVBAR (distinct from the main site and the forum) --}}
<nav x-data="{ open: false }" class="sticky top-0 z-50 border-b border-zinc-800 bg-zinc-950" aria-label="Navegação do mercado">
    <div class="w-full px-4">
        <div class="flex h-16 items-center justify-between gap-4">

            <a href="{{ route('marketplace.index') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-amber-400 text-zinc-950" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5A.75.75 0 0 1 4.5 3.75h5.379a1.5 1.5 0 0 1 1.06.44l8.122 8.121a1.5 1.5 0 0 1 0 2.122l-4.5 4.5a1.5 1.5 0 0 1-2.122 0L4.19 10.69a1.5 1.5 0 0 1-.44-1.06V4.5Z"/>
                        <circle cx="8.25" cy="8.25" r="0.75" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-sm font-semibold tracking-[0.02em] text-white">Mercado</span>
                    <span class="mt-1 text-[9px] uppercase tracking-[0.18em] text-zinc-500">{{ config('app.site_title') }}</span>
                </span>
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                <a href="{{ route('marketplace.index') }}"
                   class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.index') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                    Início
                </a>
                <a href="{{ route('marketplace.browse') }}"
                   class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.browse', 'marketplace.category') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                    Explorar
                </a>
                <a href="{{ route('marketplace.search') }}"
                   class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.search') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                    Buscar
                </a>
                @auth
                    <a href="{{ route('marketplace.mine') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.mine') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                        Meus anúncios
                    </a>
                    <a href="{{ route('marketplace.watching') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.watching') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                        Favoritos
                    </a>
                    <a href="{{ route('marketplace.messages') }}"
                       class="rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('marketplace.messages*') ? 'bg-zinc-900 text-amber-300' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                        Mensagens
                    </a>
                @endauth
            </div>

            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    <a href="{{ route('marketplace.create') }}" class="mk-btn-primary">Vender</a>
                @endauth
                <a href="{{ route('marketplace.exit') }}" class="mk-btn-outline">← Voltar ao site</a>

                @auth
                    @include('components.notification-bell')

                    <div x-data="{ userOpen: false }" class="relative">
                        <button type="button" @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu"
                                class="flex items-center gap-2 rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm transition-colors hover:border-zinc-600" aria-label="Menu da conta">
                            <span class="hidden max-w-28 truncate sm:inline">{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 opacity-70" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"/>
                            </svg>
                        </button>
                        <div x-show="userOpen" x-transition @click.outside="userOpen = false" x-cloak role="menu"
                             class="absolute right-0 mt-2 w-52 overflow-hidden rounded-md border border-zinc-800 bg-zinc-900">
                            <a href="{{ route('marketplace.mine') }}" role="menuitem" class="dropdown-link">Meus anúncios</a>
                            <a href="{{ route('marketplace.watching') }}" role="menuitem" class="dropdown-link">Favoritos</a>
                            <a href="{{ route('marketplace.messages') }}" role="menuitem" class="dropdown-link">Mensagens</a>
                            <a href="{{ route('profile.show') }}" role="menuitem" class="dropdown-link">Perfil no site</a>
                            @if (Auth::user()->admin_level >= 1)
                                <a href="{{ route('admin.dashboard') }}" role="menuitem" class="dropdown-link">Painel admin</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem" class="dropdown-link">Sair</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="mk-btn-primary">Entrar</a>
                    <a href="{{ route('register') }}" class="mk-btn-outline">Cadastrar</a>
                @endauth
            </div>

            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="marketplace-mobile-menu" aria-label="Alternar menu"
                    class="rounded-md p-2 transition-colors hover:bg-zinc-900 lg:hidden">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <div x-show="open" id="marketplace-mobile-menu" x-cloak class="space-y-1 py-4 lg:hidden">
            <a href="{{ route('marketplace.index') }}" class="mobile-link">Início</a>
            <a href="{{ route('marketplace.browse') }}" class="mobile-link">Explorar</a>
            <a href="{{ route('marketplace.search') }}" class="mobile-link">Buscar</a>
            @auth
                <a href="{{ route('marketplace.create') }}" class="mobile-link text-amber-300">+ Vender</a>
                <a href="{{ route('marketplace.mine') }}" class="mobile-link">Meus anúncios</a>
                <a href="{{ route('marketplace.watching') }}" class="mobile-link">Favoritos</a>
                <a href="{{ route('marketplace.messages') }}" class="mobile-link">Mensagens</a>
                <a href="{{ route('profile.show') }}" class="mobile-link">Perfil no site</a>
                @if (Auth::user()->admin_level >= 1)
                    <a href="{{ route('admin.dashboard') }}" class="mobile-link">Painel admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mobile-link w-full text-left">Sair</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mobile-link">Entrar</a>
                <a href="{{ route('register') }}" class="mobile-link">Cadastrar</a>
            @endauth
            <a href="{{ route('marketplace.exit') }}" class="mobile-link text-amber-300">← Voltar ao site</a>
        </div>
    </div>
</nav>

<main id="main-content">
    @yield('content')
</main>

<footer class="mt-12 border-t border-zinc-800 bg-zinc-950">
    <div class="flex w-full flex-col items-center justify-between gap-3 px-4 py-6 text-sm text-zinc-500 sm:flex-row">
        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-amber-400" aria-hidden="true"></span> Mercado · {{ config('app.site_title') }}</span>
        <nav class="flex items-center gap-5" aria-label="Rodapé do mercado">
            <a href="{{ route('marketplace.index') }}" class="transition-colors hover:text-zinc-300">Início</a>
            <a href="{{ route('marketplace.browse') }}" class="transition-colors hover:text-zinc-300">Explorar</a>
            @auth
                <a href="{{ route('marketplace.create') }}" class="transition-colors hover:text-zinc-300">Vender</a>
            @endauth
            <a href="{{ route('marketplace.exit') }}" class="font-semibold text-amber-300 transition-colors hover:text-amber-200">Voltar ao site</a>
        </nav>
    </div>
</footer>

@include('layouts.partials.interactions')

@stack('scripts')

</body>
</html>
