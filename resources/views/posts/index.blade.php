@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl border-x border-zinc-800 min-h-screen">

    {{-- Sticky header: tabs + search --}}
    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
        <nav class="flex items-center gap-1 px-2 pt-2" aria-label="Timeline">
            <a href="{{ route('posts.index', array_filter(['q' => $query ?? null])) }}"
               class="rounded-full px-4 py-1.5 text-sm font-bold transition {{ ($tab ?? 'latest') !== 'trending' ? 'bg-zinc-100 text-zinc-950' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                Latest
            </a>
            <a href="{{ route('posts.index', array_filter(['tab' => 'trending', 'q' => $query ?? null])) }}"
               class="rounded-full px-4 py-1.5 text-sm font-bold transition {{ ($tab ?? 'latest') === 'trending' ? 'bg-zinc-100 text-zinc-950' : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' }}">
                Trending
            </a>
        </nav>
        <form method="GET" action="{{ route('posts.index') }}" class="px-4 py-2.5" role="search">
            @if (($tab ?? 'latest') === 'trending')
                <input type="hidden" name="tab" value="trending">
            @endif
            <div class="relative">
                <label for="post-search" class="sr-only">Search posts</label>
                <input id="post-search" type="text" name="q" value="{{ $query ?? '' }}" maxlength="100"
                       placeholder="Search posts or authors…"
                       class="w-full rounded-full border border-zinc-800 bg-zinc-900 py-1.5 pl-9 pr-8 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                @if (!empty($query))
                    <a href="{{ route('posts.index', ($tab ?? 'latest') === 'trending' ? ['tab' => 'trending'] : []) }}"
                       aria-label="Clear search"
                       class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full px-1.5 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200">✕</a>
                @endif
            </div>
        </form>
    </div>

    @auth
        @include('posts.create')
    @else
        <div class="border-b border-zinc-800 p-6 text-center">
            <h3 class="mb-1 text-2xl font-extrabold">New to Comics?</h3>
            <p class="mb-4 text-sm text-zinc-400">Sign up now to join the conversation.</p>
            <div class="flex justify-center gap-3">
                <a href="{{ route('register') }}"
                   class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-indigo-500">
                    Create account
                </a>
                <a href="{{ route('login') }}"
                   class="rounded-full border border-zinc-700 px-5 py-2 text-sm font-bold text-zinc-200 transition hover:bg-zinc-900">
                    Sign in
                </a>
            </div>
        </div>
    @endauth

    {{-- Timeline --}}
    <div>
        @forelse ($posts as $post)
            @include('posts.post', ['post' => $post, 'likedPostIds' => $likedPostIds ?? []])
        @empty
            <div class="p-8 text-center text-zinc-500">
                @if (!empty($query))
                    No posts matching “{{ $query }}”.
                @elseif (($tab ?? 'latest') === 'trending')
                    Nothing trending this week yet.
                @else
                    No posts yet. Be the first to post!
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="flex justify-center border-t border-zinc-800 p-4">
        {{ $posts->links() }}
    </div>

</div>
@endsection
