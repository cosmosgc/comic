@extends('layouts.marketplace-layout')

@section('title', 'Mensagens — Mercado')

@php
    $me = Auth::user();
@endphp

@section('content')
<div class="mx-auto w-full max-w-4xl px-4 py-8">

    <header class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Mensagens</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Conversas sobre os anúncios.
                @if ($unread > 0)
                    <span class="font-semibold text-amber-300">{{ $unread }} não lida(s) nesta página.</span>
                @endif
            </p>
        </div>
        <a href="{{ route('marketplace.mine') }}" class="mk-btn-outline">Meus anúncios</a>
    </header>

    @if ($conversations->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-800 p-10 text-center">
            <p class="text-base font-semibold">Nenhuma conversa ainda.</p>
            <p class="mt-1 text-sm text-zinc-500">Quando alguém falar com você — ou você falar com um vendedor — a conversa aparece aqui.</p>
            <a href="{{ route('marketplace.browse') }}" class="mk-btn-primary mt-4">Explorar o mercado</a>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($conversations as $conversation)
                @php
                    $other = (int) $conversation->buyer_id === (int) $me->id ? $conversation->seller : $conversation->buyer;
                    $unreadCount = $conversation->unreadFor($me);
                    $cover = $conversation->listing?->coverUrl();
                @endphp
                <a href="{{ route('marketplace.messages.show', ['conversation' => $conversation]) }}"
                   class="mk-card flex items-center gap-3 p-3">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-zinc-800 bg-zinc-950">
                        @if ($cover)
                            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <span class="flex h-full w-full items-center justify-center text-xl opacity-50" aria-hidden="true">🏷️</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold">{{ $other?->name ?? 'Usuário' }}</p>
                            @if ($unreadCount > 0)
                                <span class="rounded bg-amber-400 px-1.5 py-0.5 text-[10px] font-bold text-zinc-950">{{ $unreadCount }}</span>
                            @endif
                        </div>
                        <p class="truncate text-xs text-zinc-500">{{ $conversation->listing?->title }}</p>
                        <p class="truncate text-xs text-zinc-500">
                            {{ $conversation->lastMessage?->body ?? 'Sem mensagens' }}
                        </p>
                    </div>

                    <span class="shrink-0 text-right text-[11px] text-zinc-500">
                        {{ $conversation->last_message_at?->diffForHumans() }}
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $conversations->links() }}</div>
    @endif

</div>
@endsection
