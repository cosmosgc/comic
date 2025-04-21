@php
    $widget = $widgets->firstWhere('position_index', $position);
@endphp

@if ($widget)
    <div class="p-3 widget-box">
        {!! $widget->content !!}
    </div>
@endif
