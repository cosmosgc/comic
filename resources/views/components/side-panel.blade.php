{{-- resources/views/components/side-panel.blade.php --}}

@props([
    'position' => 'left', // left | right
    'title' => 'Side Panel',
])

@php
    $positionClasses = $position === 'right'
        ? 'right-0 border-l'
        : 'left-0 border-r';
@endphp

<aside
    class="fixed top-0 {{ $positionClasses }} z-50 hidden h-screen w-64
           border-zinc-800 bg-zinc-900 pt-16 shadow-lg
           md:block overflow-y-auto">

    <div class="px-4 pb-6">
        <h4 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-300">
            {{ $title }}
        </h4>

        <div class="space-y-3 text-sm text-zinc-400">
            {{ $slot }}
        </div>
    </div>
</aside>
