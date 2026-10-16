{{-- Color-coded tag pill. The category supplies Flask-style
      background/border/text colors. Tags in a hidden category render
      nothing here (data stays: search and the tag editor still see them).
      Props: $tag (Tag model), $class (base classes). --}}
@props(['tag', 'class' => ''])
@php($pillCategory = $tag->displayCategory ?? null)
@if ($pillCategory === null || $pillCategory->is_visible)
<span class="{{ $class }}"
    @if ($pillCategory) style="background-color: {{ $pillCategory->background }}; border-color: {{ $pillCategory->border }}; color: {{ $pillCategory->text_color }}; border-width: 1px; border-style: solid;" title="Categoria: {{ $pillCategory->label }}" @endif>{{ $tag->name }}</span>
@endif
