@props(['listing'])

@php
    $cover = $listing->coverUrl();
    $icons = ['comic' => '📚', 'physical' => '📦', 'digital' => '💾', 'service' => '🛠️', 'other' => '✨'];
    $icon = $icons[$listing->kind] ?? '🏷️';
@endphp

<a href="{{ $listing->url() }}" class="mk-card group flex flex-col">
    <div class="relative aspect-[4/3] overflow-hidden bg-zinc-950">
        @if ($cover)
            <img src="{{ $cover }}" alt="" loading="lazy"
                 class="h-full w-full object-cover transition-opacity duration-300 group-hover:opacity-90">
        @else
            <div class="flex h-full w-full items-center justify-center text-4xl opacity-50" aria-hidden="true">{{ $icon }}</div>
        @endif

        <span class="absolute left-2 top-2 rounded border border-zinc-800 bg-zinc-950 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-zinc-300">
            {{ $listing->typeLabel() }}
        </span>

        @if ($listing->is_featured)
            <span class="absolute right-2 top-2 rounded bg-amber-400 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-zinc-950">Destaque</span>
        @endif

        @if ($listing->status === 'reserved')
            <span class="absolute right-2 top-2 rounded bg-sky-400 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-zinc-950">Reservado</span>
        @elseif ($listing->status === 'sold')
            <span class="absolute right-2 top-2 rounded bg-rose-400 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-zinc-950">Vendido</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-3.5">
        <div class="flex items-start justify-between gap-2">
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-zinc-100">{{ $listing->title }}</h3>
            <span class="shrink-0 text-sm font-semibold text-amber-300">{{ $listing->priceShort() }}</span>
        </div>

        <p class="truncate text-[11px] text-zinc-500">
            {{ $listing->category?->icon }} {{ $listing->category?->name }}
            @if ($listing->locationLabel() !== '')
                · {{ $listing->locationLabel() }}
            @endif
        </p>

        <div class="mt-auto flex items-center justify-between border-t border-zinc-800 pt-2 text-[10px] uppercase tracking-wide text-zinc-500">
            <span>{{ $listing->kindLabel() }}</span>
            <span title="Favoritos">♥ {{ $listing->watch_count }}</span>
        </div>
    </div>
</a>
