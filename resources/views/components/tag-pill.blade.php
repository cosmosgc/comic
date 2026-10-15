{{-- Color-coded tag pill. The category supplies Flask-style
      background/border/text colors; uncategorized tags keep the
      caller's Tailwind classes untouched.
      Props: $tag (Tag model), $class (base classes). --}}
@props(['tag', 'class' => ''])
@php($pillCategory = $tag->category ?? null)
<span class="{{ $class }}"
    @if ($pillCategory) style="background-color: {{ $pillCategory->background }}; border-color: {{ $pillCategory->border }}; color: {{ $pillCategory->text }}; border-width: 1px; border-style: solid;" title="Categoria: {{ $pillCategory->label }}" @endif>{{ $tag->name }}</span>
