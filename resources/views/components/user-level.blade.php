{{-- Compact level chip + top badge shown next to an author's name.
     Expects $user. Eager-load the `badges` relation to avoid N+1. --}}
@php($gamificationOn = (bool) config('gamification.enabled', true))
@if ($user && $gamificationOn)
    @php($top = $user->topBadge())
    @if ((int) ($user->level ?? 1) > 1)
        <a href="{{ route('leaderboard.index') }}"
           title="Nível {{ (int) $user->level }} · {{ number_format((int) $user->experience) }} XP"
           class="inline-flex shrink-0 items-center rounded-full bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-bold leading-none text-indigo-300 transition hover:bg-indigo-500/30">
            Lv {{ (int) $user->level }}
        </a>
    @endif
    @if ($top)
        <span class="shrink-0 text-xs" title="{{ $top->label }}">{{ $top->icon }}</span>
    @endif
@endif
