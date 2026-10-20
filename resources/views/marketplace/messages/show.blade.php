@extends('layouts.marketplace-layout')

@section('title', 'Conversa — Mercado')

@php($me = Auth::user())

@section('content')
<div class="mx-auto w-full max-w-3xl px-4 py-8">

    <nav class="mb-4 text-xs text-zinc-500" aria-label="Trilha de navegação">
        <a href="{{ route('marketplace.messages') }}" class="hover:text-zinc-300">Mensagens</a>
        <span aria-hidden="true"> / </span>
        <span class="truncate text-zinc-300">{{ $conversation->listing?->title }}</span>
    </nav>

    <header class="mk-panel mb-4 flex flex-wrap items-center justify-between gap-3 p-3">
        <div class="flex min-w-0 items-center gap-3">
            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-md border border-zinc-800 bg-zinc-950">
                @if ($conversation->listing?->coverUrl())
                    <img src="{{ $conversation->listing->coverUrl() }}" alt="" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full w-full items-center justify-center text-xl opacity-50" aria-hidden="true">🏷️</span>
                @endif
            </div>
            <div class="min-w-0">
                <a href="{{ $conversation->listing?->url() }}" class="truncate font-semibold hover:text-amber-200">{{ $conversation->listing?->title }}</a>
                <p class="text-xs text-zinc-400">
                    Com {{ $other?->name ?? 'usuário' }}
                    · <a href="{{ $conversation->listing?->url() }}" class="text-amber-300 hover:underline">ver anúncio</a>
                </p>
            </div>
        </div>
        <a href="{{ route('marketplace.messages') }}" class="mk-btn-ghost">← Inbox</a>
    </header>

    <div class="space-y-3 rounded-lg border border-zinc-800 bg-zinc-950 p-4">
        @forelse ($conversation->messages as $message)
            @php($mine = (int) $message->sender_id === (int) $me->id)
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm {{ $mine ? 'bg-amber-400 text-zinc-950' : 'border border-zinc-800 bg-zinc-900 text-zinc-100' }}">
                    @unless ($mine)
                        <p class="text-[11px] font-bold text-amber-300">{{ $message->sender?->name }}</p>
                    @endunless
                    <p class="whitespace-pre-line">{{ $message->body }}</p>
                    <p class="mt-1 text-right text-[10px] {{ $mine ? 'text-zinc-800/70' : 'text-zinc-500' }}">
                        {{ $message->created_at->format('d/m H:i') }}
                    </p>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-zinc-500">Nenhuma mensagem ainda. Diga olá!</p>
        @endforelse
    </div>

    @if ($conversation->status === 'blocked')
        <p class="mt-3 rounded-md border border-rose-900 bg-rose-950 px-3 py-2 text-sm text-rose-200">
            Esta conversa está bloqueada.
        </p>
    @else
        <form method="POST" action="{{ route('marketplace.messages.store', ['conversation' => $conversation]) }}" class="mt-4 space-y-2">
            @csrf
            <label for="message-body" class="sr-only">Mensagem</label>
            <textarea id="message-body" name="body" rows="3" required minlength="2" maxlength="4000" class="mk-input"
                      placeholder="Escreva sua mensagem…">{{ old('body') }}</textarea>
            @error('body')<p class="text-xs text-rose-400">{{ $message }}</p>@enderror
            <div class="flex justify-end">
                <button type="submit" class="mk-btn-primary">Enviar</button>
            </div>
        </form>
    @endif

    <p class="mt-4 text-xs text-zinc-500">
        {{ config('marketplace.safety_note') }}
    </p>

</div>
@endsection
