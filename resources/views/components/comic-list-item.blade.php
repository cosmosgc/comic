{{-- Horizontal comic row for the home list layout: cover on the left,
      info on the right. Props: $comic, $likedComicIds (optional).
      The like button reuses the card's delegated handler ([data-comic-like])
      and reading progress paints via [data-comic-card], same as the grid. --}}
@php
    $locked = $comic->isLockedFor(auth()->user());
    $comicUrl = $locked ? route('login') : route('comics.showBySlug', ['slug' => $comic->slug]);
@endphp
<div
    data-comic-card="{{ $comic->id }}"
    data-page-count="{{ $comic->pageCount() }}"
    data-comic-list
    class="flex gap-4 rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow transition duration-300 hover:border-indigo-500/60 sm:p-4"
>
    <!-- Cover -->
    <a href="{{ $comicUrl }}"
       title="{{ $comic->title }}"
       class="relative shrink-0">
        <img
            src="{{ asset('storage/' . $comic->image_path) }}"
            alt="{{ $comic->title }} cover"
            loading="lazy"
            class="aspect-[3/4] h-36 w-24 rounded-lg object-cover sm:h-44 sm:w-32"
        >
        <x-comic-ribbons :comic="$comic" variant="overlay" />
        @if ($locked)
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-lg bg-zinc-950/70 text-center">
                <svg class="h-6 w-6 text-fuchsia-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 00-9 0v3m-.75 0h10.5a1.5 1.5 0 011.5 1.5v6a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5v-6a1.5 1.5 0 011.5-1.5z"/>
                </svg>
                <span class="px-1 text-[10px] font-bold uppercase tracking-wide text-fuchsia-200">Entre para ler</span>
            </span>
        @endif
    </a>

    <!-- Info -->
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex items-center gap-2 text-sm">
            <img
                src="{{ $comic->user->avatar_image_path ? asset($comic->user->avatar_image_path) : asset('default-avatar.png') }}"
                alt="{{ $comic->user->name }} avatar"
                class="h-6 w-6 rounded-full border border-zinc-700 object-cover"
            >
            <a href="{{ route('profile.public.show.username', ['username' => $comic->user->name]) }}"
               class="truncate font-medium text-zinc-300 hover:opacity-80">
                {{ $comic->user->name }}
            </a>
            <x-comic-ribbons :comic="$comic" variant="inline" />
            @if ($comic->isScheduled())
                <span class="shrink-0 text-xs font-medium text-amber-300">
                    ⏳ Agendado{{ $comic->published_at ? ' · '.$comic->published_at->format('d/m H:i') : '' }}
                </span>
            @endif
        </div>

        <a href="{{ $comicUrl }}" title="{{ $comic->title }}">
            <h2 class="mt-1 truncate text-lg font-semibold leading-tight hover:text-indigo-400">
                {{ $comic->title }}
            </h2>
        </a>

        <p class="truncate text-sm text-zinc-400">
            Por {{ $comic->author }}
            <span class="text-xs text-zinc-500">· {{ $comic->created_at }}</span>
        </p>

        @if ($comic->description)
            <p class="mt-1 hidden text-sm text-zinc-400 line-clamp-2 sm:block">
                {{ $comic->description }}
            </p>
        @endif

        @if ($comic->tags->isNotEmpty())
            <div class="mt-2 hidden flex-wrap gap-2 sm:flex">
                @foreach ($comic->tags as $tag)
                    @include('components.tag-pill', [
                        'tag' => $tag,
                        'class' => 'rounded-full bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-300',
                    ])
                @endforeach
            </div>
        @endif

        @if (! $locked)
            <!-- Reading progress (cookie-based, painted by JS) -->
            <div class="mt-2" data-reading-progress>
                <div class="mb-1 flex items-center justify-between text-[11px] font-medium">
                    <span data-reading-label class="text-zinc-400"></span>
                    <span data-reading-pct class="text-zinc-500"></span>
                </div>
                <div class="h-1 overflow-hidden rounded-full bg-zinc-800" role="progressbar"
                     aria-label="Progresso de leitura de {{ $comic->title }}" aria-valuemin="0" aria-valuemax="100" data-reading-bar>
                    <div data-reading-fill class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>
        @endif

        <!-- Engagement: likes + comments (same delegated handler as the grid cards) -->
        @php
            $rowLiked = in_array($comic->id, $likedComicIds ?? []);
            $rowLikeCount = $comic->liked_by_users_count ?? 0;
            $rowCommentCount = $comic->comments_count ?? 0;
            $rowCollectionCount = $comic->collections_count ?? 0;
        @endphp
        <div class="mt-2 flex items-center gap-2 text-sm text-zinc-400 sm:gap-4">
            @auth
                <button type="button"
                        data-comic-like="{{ $comic->id }}"
                        data-like-url="{{ route('comics.like', $comic) }}"
                        data-csrf="{{ csrf_token() }}"
                        data-liked="{{ $rowLiked ? '1' : '0' }}"
                        aria-label="{{ $rowLiked ? 'Descurtir' : 'Curtir' }} {{ $comic->title }}"
                        aria-pressed="{{ $rowLiked ? 'true' : 'false' }}"
                        class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1 rounded-lg transition {{ $rowLiked ? 'text-rose-500' : 'hover:text-rose-500 active:bg-zinc-800' }}">
                    <svg class="h-[18px] w-[18px]" fill="{{ $rowLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <span data-like-count>{{ $rowLikeCount > 0 ? $rowLikeCount : '' }}</span>
                </button>
            @else
                <span class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1" title="{{ $rowLikeCount }} likes">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <span>{{ $rowLikeCount > 0 ? $rowLikeCount : '' }}</span>
                </span>
            @endauth
            <a href="{{ $comicUrl }}" title="Comentários" aria-label="Comentários em {{ $comic->title }}" class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1 rounded-lg transition hover:text-sky-400 active:bg-zinc-800">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8m-8 0a8 8 0 108-8m-8 8V4m0 8l-4-4m4 4l4-4"/>
                </svg>
                <span>{{ $rowCommentCount > 0 ? $rowCommentCount : '' }}</span>
            </a>
            <span class="inline-flex items-center gap-1" title="Em {{ $rowCollectionCount }} coleção(ões)">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span>{{ $rowCollectionCount > 0 ? $rowCollectionCount : '' }}</span>
            </span>
            @if ($locked)
                <a href="{{ route('login') }}"
                   class="ml-auto inline-flex min-h-[40px] items-center rounded-lg bg-fuchsia-600 px-3 text-sm font-semibold text-white transition hover:bg-fuchsia-500">
                    Entrar
                </a>
            @else
                <a href="{{ $comicUrl }}"
                   class="ml-auto inline-flex min-h-[40px] items-center rounded-lg bg-indigo-600 px-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Ler
                </a>
            @endif
        </div>
    </div>
</div>
