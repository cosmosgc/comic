@extends('layouts.forum-layout')

@section('title', 'Fórum — '.config('app.site_title'))

@section('content')
<div class="mx-auto min-h-screen max-w-4xl px-4 py-6">

    <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Fórum</h1>
            <p class="text-sm text-zinc-400">Converse com a comunidade fora do feed de comics.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('forum.search') }}" class="rounded-full border border-zinc-700 px-4 py-1.5 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-900">Buscar</a>
            @auth
                <a href="{{ route('forum.unread') }}" class="rounded-full border border-zinc-700 px-4 py-1.5 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-900">Não lidos</a>
            @endauth
        </div>
    </header>

    @forelse ($sections as $section)
        <section class="mb-6 overflow-hidden rounded-xl border border-zinc-800">
            <div class="flex items-center gap-2 border-b border-zinc-800 bg-zinc-900/50 px-4 py-3">
                @if ($section->icon)<span aria-hidden="true">{{ $section->icon }}</span>@endif
                <h2 class="font-bold">{{ $section->name }}</h2>
            </div>
            @if ($section->description)
                <p class="border-b border-zinc-800 px-4 py-2 text-xs text-zinc-400">{{ $section->description }}</p>
            @endif
            <div class="divide-y divide-zinc-800">
                @forelse ($section->children as $board)
                    @php($last = $board->threads->first())
                    <a href="{{ route('forum.category', ['categorySlug' => $board->slug]) }}"
                       class="flex items-center gap-4 px-4 py-3 transition hover:bg-zinc-900/40">
                        @if ($board->banner_image_path)
                            <div class="h-10 w-20 shrink-0 overflow-hidden rounded-lg border border-zinc-800" aria-hidden="true">
                                <img src="{{ asset($board->banner_image_path) }}" alt="" class="h-full w-full object-cover"
                                     style="object-position: {{ $board->bannerPosition() }};">
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-zinc-800 text-lg" aria-hidden="true">
                                {{ $board->icon ?: '💬' }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">
                                {{ $board->name }}
                                @unless ($board->is_visible)
                                    <span class="ml-1 rounded bg-amber-500/20 px-1.5 py-0.5 text-xs font-bold text-amber-300">oculto</span>
                                @endunless
                                @if ($board->is_locked)
                                    <span class="ml-1 rounded bg-rose-500/20 px-1.5 py-0.5 text-xs font-bold text-rose-300">trancado</span>
                                @endif
                            </p>
                            @if ($board->description)
                                <p class="truncate text-xs text-zinc-400">{{ $board->description }}</p>
                            @endif
                        </div>
                        <div class="hidden shrink-0 text-right text-xs text-zinc-400 sm:block">
                            <p><span class="font-bold text-zinc-200">{{ $board->threads_count }}</span> tópicos</p>
                            <p><span class="font-bold text-zinc-200">{{ $board->post_count }}</span> posts</p>
                        </div>
                        <div class="hidden min-w-0 max-w-[14rem] shrink-0 text-right text-xs text-zinc-400 lg:block">
                            @if ($last)
                                <p class="truncate font-medium text-zinc-300">{{ $last->title }}</p>
                                <p>{{ $last->last_post_at?->diffForHumans() ?? $last->created_at->diffForHumans() }}</p>
                            @else
                                <p class="opacity-60">Sem tópicos</p>
                            @endif
                        </div>
                    </a>
                @empty
                    <p class="px-4 py-3 text-sm text-zinc-500">Nenhum quadro nesta seção.</p>
                @endforelse
            </div>
        </section>
    @empty
        <div class="rounded-xl border border-zinc-800 p-8 text-center text-zinc-500">
            O fórum ainda não tem categorias.
        </div>
    @endforelse

    <div class="grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl border border-zinc-800 p-3">
            <p class="text-xl font-bold">{{ number_format($stats['threads']) }}</p>
            <p class="text-xs text-zinc-400">Tópicos</p>
        </div>
        <div class="rounded-xl border border-zinc-800 p-3">
            <p class="text-xl font-bold">{{ number_format($stats['posts']) }}</p>
            <p class="text-xs text-zinc-400">Posts</p>
        </div>
        <div class="rounded-xl border border-zinc-800 p-3">
            <p class="text-xl font-bold">{{ number_format($stats['members']) }}</p>
            <p class="text-xs text-zinc-400">Membros</p>
        </div>
    </div>

</div>
@endsection
