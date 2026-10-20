@props(['listing'])

@php
    $cover = $listing->coverUrl();
    $icons = ['comic' => '📚', 'physical' => '📦', 'digital' => '💾', 'service' => '🛠️', 'other' => '✨'];
    $icon = $icons[$listing->kind] ?? '🏷️';
@endphp

<a href="{{ $listing->url() }}" class="mk-card group flex flex-col">
    <div class="relative aspect-[4/3] overflow-hidden bg-zinc-800">
        @if ($cover)
            <img src="{{ $cover }}" alt="" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <div class="flex h-full w-full items-center justify-center text-4xl opacity-60" aria-hidden="true">{{ $icon }}</div>
        @endif

        <span class="absolute left-2 top-2 rounded-full bg-zinc-950/80 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-amber-300">
            {{ $listing->typeLabel() }}
        </span>

        @if ($listing->is_featured)
            <span class="absolute right-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-zinc-950">Destaque</span>
        @endif

        @if ($listing->status === 'reserved')
            <span class="absolute right-2 top-2 rounded-full bg-sky-500 px-2 py-0.5 text-[11px] font-bold text-white">Reservado</span>
        @elseif ($listing->status === 'sold')
            <span class="absolute right-2 top-2 rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">Vendido</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-1.5 p-3">
        <div class="flex items-start justify-between gap-2">
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-zinc-100">{{ $listing->title }}</h3>
            <span class="shrink-0 text-sm font-extrabold text-amber-300">{{ $listing->priceShort() }}</span>
        </div>

        <p class="truncate text-xs text-zinc-400">
            {{ $listing->category?->icon }} {{ $listing->category?->name }}
            @if ($listing->locationLabel() !== '')
                · {{ $listing->locationLabel() }}
            @endif
        </p>

        <div class="mt-auto flex items-center justify-between pt-1 text-[11px] text-zinc-500">
            <span>{{ $listing->kindLabel() }}</span>
            <span title="Favoritos">♥ {{ $listing->watch_count }}</span>
        </div>
    </div>
</a>
