@php
    $widget = $widgets->firstWhere('position_index', $position);
@endphp

@if ($widget)
    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow">
        {!! $widget->content !!}
    </div>
@endif
