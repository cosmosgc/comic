{{--
    Status ribbons for a comic, shared by the grid card and the list row.
    Props:
      - comic:   the Comic being rendered
      - variant: 'overlay' (stacked over a cover) or 'inline' (a row, used
                 by the horizontal list layout)

    "Em breve" and "Pré-estreia" are the same state seen from a different
    viewer: members (and owners/admins) get the last week before release.
    Exclusive combines with either of them, plus "Novo" once released.
--}}
@php
    $variant = $variant ?? 'overlay';
    $viewer = auth()->user();

    $scheduled = $comic->isScheduled();
    $earlyAccess = $scheduled && $viewer !== null && $comic->isInEarlyAccessWindow();
    $fresh = ! $scheduled && $comic->isNew();

    $pill = 'inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide ring-1';
    $icon = 'h-3 w-3 shrink-0';
@endphp

@if ($comic->isExclusive() || $scheduled || $fresh)
    <div class="{{ $variant === 'overlay'
        ? 'pointer-events-none absolute left-2 top-2 z-20 flex flex-col items-start gap-1'
        : 'flex flex-wrap items-center gap-1' }}"
         data-comic-ribbons>

        @if ($comic->isExclusive())
            <span class="{{ $pill }} bg-fuchsia-500/90 text-white ring-fuchsia-400/40"
                  title="Conteúdo exclusivo para membros">
                <svg class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 00-9 0v3m-.75 0h10.5a1.5 1.5 0 011.5 1.5v6a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5v-6a1.5 1.5 0 011.5-1.5z"/>
                </svg>
                Exclusivo
            </span>
        @endif

        @if ($scheduled)
            <span class="{{ $pill }} {{ $earlyAccess
                    ? 'bg-sky-400/95 text-sky-950 ring-sky-200/50'
                    : 'bg-amber-400/95 text-amber-950 ring-amber-200/50' }}"
                  title="{{ $earlyAccess
                    ? 'Pré-estreia para membros - liberação pública em '.$comic->published_at->format('d/m/Y H:i')
                    : 'Ainda não lançado - lança em '.$comic->published_at->format('d/m/Y H:i') }}">
                @if ($earlyAccess)
                    <svg class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 2.5L4.5 13.5H11l-1 8 8.5-11H12l1-8z"/>
                    </svg>
                    Pré-estreia
                @else
                    <svg class="{{ $icon }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.5V12l3.5 2.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Em breve
                @endif
            </span>
        @elseif ($fresh)
            <span class="{{ $pill }} bg-emerald-400/95 text-emerald-950 ring-emerald-200/50"
                  title="Lançado nos últimos {{ \App\Models\Comic::NEW_WINDOW_DAYS }} dias">
                <svg class="{{ $icon }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2l2.9 6.2 6.6.8-4.9 4.6 1.3 6.6L12 17l-5.9 3.2 1.3-6.6-4.9-4.6 6.6-.8L12 2z"/>
                </svg>
                Novo
            </span>
        @endif
    </div>
@endif
