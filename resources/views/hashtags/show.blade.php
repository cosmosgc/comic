@extends('layouts.app')

@section('title', '#'.$hashtag->tag)

@section('content')
<div class="mx-auto max-w-xl border-x border-zinc-800 min-h-screen">

    <div class="sticky top-16 z-40 border-b border-zinc-800 bg-zinc-950/80 px-4 py-3 backdrop-blur">
        <a href="{{ route('posts.index') }}" class="text-sm text-zinc-400 transition hover:text-zinc-100">← Comunidade</a>
        <h2 class="mt-1 text-xl font-bold">#{{ $hashtag->tag }}</h2>
        <p class="text-sm text-zinc-500">{{ $hashtag->use_count }} post(s)</p>
    </div>

    @forelse ($posts as $post)
        @include('posts.post', ['post' => $post, 'likedPostIds' => $likedPostIds ?? [], 'bookmarkedPostIds' => $bookmarkedPostIds ?? []])
    @empty
        <div class="p-8 text-center text-zinc-500">
            Nenhum post com essa hashtag ainda.
        </div>
    @endforelse

    @if ($posts->hasPages())
        <div class="flex justify-center border-t border-zinc-800 p-4">
            {{ $posts->links() }}
        </div>
    @endif

</div>
@endsection
