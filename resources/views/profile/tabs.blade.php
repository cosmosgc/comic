{{-- Profile tabs: Comics | Liked posts | Collections.
     Expects $activeTab ('comics'|'likes'|'collections'), $likesTab (bool),
     and $collectionsTab (bool). --}}
<div class="mb-6 flex items-center gap-1 border-b border-zinc-800">
    <a href="{{ request()->url() }}"
       class="px-4 py-2 text-sm font-semibold transition
              {{ $activeTab === 'comics'
                  ? 'border-b-2 border-indigo-500 text-zinc-100'
                  : 'text-zinc-500 hover:text-zinc-200' }}">
        Comics
    </a>
    @if ($likesTab ?? true)
        <a href="{{ request()->url() }}?tab=likes"
           class="px-4 py-2 text-sm font-semibold transition
                  {{ $activeTab === 'likes'
                      ? 'border-b-2 border-indigo-500 text-zinc-100'
                      : 'text-zinc-500 hover:text-zinc-200' }}">
            Liked posts
        </a>
    @endif
    @if ($collectionsTab ?? false)
        <a href="{{ request()->url() }}?tab=collections"
           class="px-4 py-2 text-sm font-semibold transition
                  {{ $activeTab === 'collections'
                      ? 'border-b-2 border-indigo-500 text-zinc-100'
                      : 'text-zinc-500 hover:text-zinc-200' }}">
            Collections
        </a>
    @else
        <span class="cursor-not-allowed px-4 py-2 text-sm text-zinc-600" title="Coming soon">
            Collections
            <span class="ml-1 rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">soon</span>
        </span>
    @endif
</div>
