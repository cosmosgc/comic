@extends('layouts.app')

@section('title', 'Tópicos não lidos — Fórum')

@section('content')
<div class="mx-auto min-h-screen max-w-3xl px-4 py-6">

    <h1 class="mb-4 text-2xl font-extrabold tracking-tight">Tópicos não lidos</h1>

    <div class="divide-y divide-zinc-800 overflow-hidden rounded-xl border border-zinc-800">
        @forelse ($threads as $thread)
            <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}"
               class="block px-4 py-3 transition hover:bg-zinc-900/40">
                <p class="font-semibold">{{ $thread->title }}</p>
                <p class="mt-0.5 text-xs text-zinc-400">
                    em {{ $thread->category?->name }} · {{ $thread->last_post_at?->diffForHumans() ?? $thread->created_at->diffForHumans() }}
                </p>
            </a>
        @empty
            <p class="p-8 text-center text-zinc-500">Você está em dia. Nada novo por aqui.</p>
        @endforelse
    </div>

</div>
@endsection
