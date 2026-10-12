{{-- Twitter-style link card. Expects $preview from LinkPreview::forText(). --}}
@if (!empty($preview))
    @if ($preview['type'] === 'youtube')
        <a href="{{ $preview['url'] }}" target="_blank" rel="noopener nofollow"
           class="mt-3 block overflow-hidden rounded-2xl border border-zinc-800 transition hover:border-zinc-600">
            <span class="relative block">
                <img src="{{ $preview['thumbnail'] }}" alt="YouTube preview: {{ $preview['title'] }}"
                     loading="lazy" class="max-h-72 w-full object-cover">
                <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-black/70">
                        <svg class="ml-1 h-6 w-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </span>
                </span>
            </span>
            <span class="block px-3 py-2">
                <span class="block truncate text-sm font-medium text-zinc-100">{{ $preview['title'] }}</span>
                <span class="mt-0.5 block truncate text-xs text-zinc-500">youtube.com</span>
            </span>
        </a>
    @elseif ($preview['type'] === 'comic')
        <a href="{{ $preview['link'] }}"
           class="mt-3 flex gap-3 overflow-hidden rounded-2xl border border-zinc-800 p-3 transition hover:border-indigo-500/60 hover:bg-zinc-900/40">
            @if ($preview['cover'])
                <img src="{{ $preview['cover'] }}" alt="{{ $preview['title'] }} cover"
                     loading="lazy" class="h-24 w-16 shrink-0 rounded-lg object-cover">
            @endif
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-zinc-100">{{ $preview['title'] }}</span>
                @if ($preview['author'])
                    <span class="mt-0.5 block truncate text-xs text-zinc-400">by {{ $preview['author'] }}</span>
                @endif
                <span class="mt-1 inline-block rounded-full bg-indigo-500/10 px-2 py-0.5 text-[11px] font-medium text-indigo-300">Comic</span>
            </span>
        </a>
    @else
        <a href="{{ $preview['url'] }}" target="_blank" rel="noopener nofollow"
           class="mt-3 flex items-center gap-3 overflow-hidden rounded-2xl border border-zinc-800 px-3 py-2.5 transition hover:border-zinc-600">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-800" aria-hidden="true">
                <svg class="h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                </svg>
            </span>
            <span class="min-w-0">
                <span class="block truncate text-sm text-zinc-200">{{ $preview['url'] }}</span>
                <span class="block truncate text-xs text-zinc-500">{{ $preview['host'] }}</span>
            </span>
        </a>
    @endif
@endif
