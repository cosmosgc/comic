{{-- Profile tabs: Comics | Liked posts | Collections | Followers | Following.
     Expects $activeTab ('comics'|'likes'|'collections'|'followers'|'following'),
     $likesTab (bool), $collectionsTab (bool), $followsTab (bool),
     $followersCount, $followingCount. --}}
<div class="mb-6 flex flex-wrap items-center gap-1 border-b border-zinc-800">
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
            Curtidos
        </a>
    @endif
    @if ($collectionsTab ?? false)
        <a href="{{ request()->url() }}?tab=collections"
           class="px-4 py-2 text-sm font-semibold transition
                  {{ $activeTab === 'collections'
                      ? 'border-b-2 border-indigo-500 text-zinc-100'
                      : 'text-zinc-500 hover:text-zinc-200' }}">
            Coleções
        </a>
    @else
        <span class="cursor-not-allowed px-4 py-2 text-sm text-zinc-600" title="Em breve">
            Coleções
            <span class="ml-1 rounded bg-zinc-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">em breve</span>
        </span>
    @endif
    @if ($followsTab ?? false)
        <a href="{{ request()->url() }}?tab=followers"
           class="px-4 py-2 text-sm font-semibold transition
                  {{ $activeTab === 'followers'
                      ? 'border-b-2 border-indigo-500 text-zinc-100'
                      : 'text-zinc-500 hover:text-zinc-200' }}">
            Seguidores <span class="text-zinc-500">({{ $followersCount ?? 0 }})</span>
        </a>
        <a href="{{ request()->url() }}?tab=following"
           class="px-4 py-2 text-sm font-semibold transition
                  {{ $activeTab === 'following'
                      ? 'border-b-2 border-indigo-500 text-zinc-100'
                      : 'text-zinc-500 hover:text-zinc-200' }}">
            Seguindo <span class="text-zinc-500">({{ $followingCount ?? 0 }})</span>
        </a>
    @endif
</div>
