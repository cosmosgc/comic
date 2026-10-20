{{-- Focal-point controls for forum banners. The range inputs feed
     object-position on the preview image (and on the live hero when given). --}}
@props([
    'previewId',
    'heroId' => null,
    'x' => 50,
    'y' => 50,
    'nameX' => 'banner_position_x',
    'nameY' => 'banner_position_y',
])

@php
    $x = max(0, min(100, (int) $x));
    $y = max(0, min(100, (int) $y));
    $uid = 'crop-'.preg_replace('/[^a-z0-9\-]/i', '-', $previewId);
@endphp

<div class="mt-2 space-y-1" data-banner-crop data-preview="{{ $previewId }}" @if ($heroId) data-hero="{{ $heroId }}" @endif>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-[11px] text-zinc-500" for="{{ $uid }}-x">
            <span class="flex items-center justify-between">
                Horizontal
                <output for="{{ $uid }}-x" data-crop-x-output>{{ $x }}%</output>
            </span>
            <input id="{{ $uid }}-x" type="range" min="0" max="100" step="1" name="{{ $nameX }}"
                   value="{{ $x }}" data-crop-x class="mt-1 w-full accent-indigo-500">
        </label>
        <label class="block text-[11px] text-zinc-500" for="{{ $uid }}-y">
            <span class="flex items-center justify-between">
                Vertical
                <output for="{{ $uid }}-y" data-crop-y-output>{{ $y }}%</output>
            </span>
            <input id="{{ $uid }}-y" type="range" min="0" max="100" step="1" name="{{ $nameY }}"
                   value="{{ $y }}" data-crop-y class="mt-1 w-full accent-indigo-500">
        </label>
    </div>
    <p class="text-[11px] text-zinc-500">Arraste para escolher o ponto da imagem que fica visível no recorte.</p>
</div>

<script>
(function () {
    document.querySelectorAll('[data-banner-crop]').forEach(function (root) {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';

        var preview = document.getElementById(root.dataset.preview);
        var hero = root.dataset.hero ? document.getElementById(root.dataset.hero) : null;
        var x = root.querySelector('[data-crop-x]');
        var y = root.querySelector('[data-crop-y]');
        var xOut = root.querySelector('[data-crop-x-output]');
        var yOut = root.querySelector('[data-crop-y-output]');
        if (!x || !y) return;

        function apply() {
            var position = x.value + '% ' + y.value + '%';
            if (preview) preview.style.objectPosition = position;
            if (hero) hero.style.objectPosition = position;
            if (xOut) xOut.textContent = x.value + '%';
            if (yOut) yOut.textContent = y.value + '%';
        }

        x.addEventListener('input', apply);
        y.addEventListener('input', apply);
        apply();
    });
})();
</script>
