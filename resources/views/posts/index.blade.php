@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl border-x border-zinc-800 min-h-screen">

    {{-- Sticky header --}}
    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur">
        <h2 class="px-4 py-3 text-xl font-bold">Home</h2>
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
                No posts yet. Be the first to post!
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="flex justify-center border-t border-zinc-800 p-4">
        {{ $posts->links() }}
    </div>

</div>
@endsection
