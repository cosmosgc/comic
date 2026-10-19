@if ($poll ?? null)
@php
    $total = (int) $poll->options->sum('votes_count');
    $closed = $poll->isClosed();
    $canVote = auth()->check() && ! $closed && ! $thread->is_locked;
    $selected = array_map('intval', $viewerPollVotes ?? []);
@endphp

<section class="mb-4 rounded-xl border border-zinc-800 p-4" aria-label="Enquete">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold">{{ $poll->question }}</h2>
            <p class="mt-0.5 text-xs text-zinc-500">
                {{ $total }} {{ $total === 1 ? 'voto' : 'votos' }}
                @if ($poll->multiple) · múltipla escolha @endif
                @if ($poll->closes_at)
                    · {{ $closed ? 'encerrada' : 'encerra' }} {{ $poll->closes_at->diffForHumans() }}
                @endif
            </p>
        </div>
        @if ($poll->multiple)
            <span class="shrink-0 rounded-full border border-zinc-700 px-2 py-0.5 text-[11px] text-zinc-400">múltipla</span>
        @endif
    </div>

    <form method="POST" action="{{ route('forum.polls.vote', $thread) }}" class="space-y-2">
        @csrf

        @foreach ($poll->options as $option)
            @php($pct = $total > 0 ? (int) round($option->votes_count / $total * 100) : 0)
            <label class="block cursor-pointer rounded-lg border p-2 transition {{ in_array((int) $option->id, $selected, true) ? 'border-indigo-500/60 bg-indigo-500/10' : 'border-zinc-800 hover:border-zinc-600' }}">
                <span class="flex items-center gap-2 text-sm">
                    <input type="{{ $poll->multiple ? 'checkbox' : 'radio' }}"
                           name="options[]"
                           value="{{ $option->id }}"
                           @checked(in_array((int) $option->id, $selected, true))
                           @disabled(! $canVote)>
                    <span class="flex-1">{{ $option->label }}</span>
                    <span class="shrink-0 text-xs text-zinc-400">{{ $option->votes_count }} · {{ $pct }}%</span>
                </span>
                <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-zinc-800">
                    <span class="block h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></span>
                </span>
            </label>
        @endforeach

        @if ($canVote)
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <button type="submit" class="rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-500">
                    Votar
                </button>
                @if ($selected !== [])
                    <span class="text-xs text-zinc-500">Enviar de novo atualiza seu voto.</span>
                @endif
            </div>
        @elseif (! auth()->check() && ! $closed)
            <p class="pt-1 text-xs text-zinc-500">
                <a href="{{ route('login') }}" class="font-semibold text-indigo-400 hover:underline">Entre</a> para votar.
            </p>
        @endif

        @if ($errors->has('poll') || $errors->has('options'))
            <p class="text-xs text-rose-400">{{ $errors->first('poll') ?: $errors->first('options') }}</p>
        @endif
    </form>
</section>
@endif
