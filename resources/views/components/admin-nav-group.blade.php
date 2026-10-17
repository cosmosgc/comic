{{-- Collapsible admin sidebar section. `open` mirrors whether the
     current route lives inside this group, so the group you are
     working in is always expanded on load; `active` tints the label
     the same way the active link is highlighted. --}}
@props(['label', 'open' => false, 'active' => false])

<details {{ $open ? 'open' : '' }} {{ $attributes->merge(['class' => 'admin-nav-group']) }}>
    <summary aria-label="Alternar seção {{ $label }}">
        <span class="{{ $active ? 'text-indigo-600 dark:text-indigo-300' : '' }}">{{ $label }}</span>
        <svg class="admin-nav-group-chevron {{ $active ? 'text-indigo-600 dark:text-indigo-300' : '' }}"
             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </summary>
    <div class="admin-nav-group-items">
        {{ $slot }}
    </div>
</details>
