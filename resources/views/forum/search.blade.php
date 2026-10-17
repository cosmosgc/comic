@extends('layouts.app')

@section('title', 'Buscar no fórum')

@section('content')
<div class="mx-auto min-h-screen max-w-3xl px-4 py-6">

    <h1 class="mb-4 text-2xl font-extrabold tracking-tight">Buscar no fórum</h1>

    <form method="GET" action="{{ route('forum.search') }}" class="mb-6" role="search">
        <input type="text" name="q" value="{{ $query }}" maxlength="100" placeholder="Título, mensagem, autor ou tag…"
               class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
    </form>

    @if ($query !== '')
        <p class="mb-3 text-sm text-zinc-400">{{ $threads->total() }} resultado(s) para “{{ $query }}”.</p>
    @endif

    <div class="divide-y divide-zinc-800 overflow-hidden rounded-xl border border-zinc-800">
        @forelse ($threads as $thread)
            <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}"
               class="block px-4 py-3 transition hover:bg-zinc-900/40">
                <p class="font-semibold">{{ $thread->title }}</p>
                <p class="mt-0.5 text-xs text-zinc-400">
                    em {{ $thread->category?->name }} · por {{ $thread->author?->name ?? 'Usuário' }} · {{ $thread->created_at->diffForHumans() }}
                </p>
            </a>
        @empty
            <p class="p-8 text-center text-zinc-500">{{ $query !== '' ? 'Nada encontrado.' : 'Digite algo para buscar.' }}</p>
        @endforelse
    </div>

    <div class="mt-4 flex justify-center">{{ $threads->links() }}</div>

</div>
@endsection
