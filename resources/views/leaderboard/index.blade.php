@extends('layouts.app')

@section('title', 'Ranking')

@section('content')
<div class="mx-auto max-w-3xl">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">🏆 Ranking</h1>
        <div class="inline-flex overflow-hidden rounded-lg border border-zinc-800">
            <a href="{{ route('leaderboard.index') }}"
               class="{{ $tab === 'all-time' ? 'bg-indigo-600 text-white' : 'text-zinc-300 hover:bg-zinc-800' }} px-4 py-2 text-sm font-semibold">
                Geral
            </a>
            <a href="{{ route('leaderboard.index', ['tab' => 'weekly']) }}"
               class="{{ $tab === 'weekly' ? 'bg-indigo-600 text-white' : 'text-zinc-300 hover:bg-zinc-800' }} px-4 py-2 text-sm font-semibold">
                Últimos 7 dias
            </a>
        </div>
    </div>

    @if ($myRank !== null)
        <p class="mb-4 text-sm text-zinc-400">Você está na <span class="font-bold text-indigo-300">#{{ $myRank }}</span> posição.</p>
    @endif

    @if ($tab === 'weekly')
        <p class="mb-4 text-sm text-zinc-500">XP ganho pelas atividades registradas nos últimos 7 dias.</p>
    @endif

    <div class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900">
        @forelse ($users as $index => $user)
            @php($rank = $index + 1)
            @php($isMe = $viewer && (int) $viewer->id === (int) $user->id)
            @php($points = $tab === 'weekly' ? (int) ($user->weekly_exp ?? 0) : (int) $user->experience)
            @php($avatar = $user->avatar_image_path ? asset($user->avatar_image_path) : Vite::asset('resources/images/default-avatar.svg'))
            <a href="{{ route('profile.public.show.username', ['username' => $user->name]) }}"
               class="flex items-center gap-3 border-b border-zinc-800 px-4 py-3 transition last:border-0 hover:bg-zinc-800/50 {{ $isMe ? 'bg-indigo-600/10' : '' }}">
                <span class="w-8 shrink-0 text-center text-sm font-bold {{ $rank <= 3 ? 'text-amber-400' : 'text-zinc-500' }}">
                    @if ($rank === 1) 🥇 @elseif ($rank === 2) 🥈 @elseif ($rank === 3) 🥉 @else #{{ $rank }} @endif
                </span>
                <img src="{{ $avatar }}" alt="" class="h-10 w-10 shrink-0 rounded-full border border-zinc-800 object-cover">
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1 truncate font-semibold text-zinc-100">
                        {{ $user->name }}
                        @include('components.user-level', ['user' => $user, 'link' => false])
                    </p>
                    <p class="text-xs text-zinc-500">
                        @if ($tab === 'weekly')
                            {{ number_format($points) }} XP esta semana
                        @else
                            Nível {{ (int) $user->level }}
                            @if ((int) $user->current_streak > 0) · 🔥 {{ (int) $user->current_streak }} @endif
                        @endif
                    </p>
                </div>
                <span class="shrink-0 text-sm font-bold text-indigo-300">{{ number_format($points) }} XP</span>
            </a>
        @empty
            <p class="px-4 py-10 text-center text-sm text-zinc-500">Ainda não há atividades suficientes para o ranking.</p>
        @endforelse
    </div>
</div>
@endsection
