{{-- Hamburger dropdown for the site navbar: one button per section, each
     opening its own menu (same pattern as the profile dropdown). --}}
@props(['label', 'id'])

<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            aria-haspopup="menu" aria-controls="{{ $id }}"
            class="flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-800 hover:text-white"
            aria-label="Menu {{ $label }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>
        {{ $label }}
    </button>

    <div x-show="open" x-transition @click.outside="open = false"
         @keydown.escape.window="open = false" x-cloak
         id="{{ $id }}" role="menu" aria-label="{{ $label }}"
         class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900 shadow-xl">
        {{ $slot }}
    </div>
</div>
