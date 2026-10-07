<div 
    class="comic-card group relative flex flex-col rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow transition hover:border-indigo-500"
    data-comic-id="{{ $comic->id }}"
    data-comic-pagecount="{{ $comic->pageCount() }}"
>

    <!-- Progress background (if you animate later) -->
    <div class="progress-background absolute inset-x-0 bottom-0 h-0 -z-10 transition-all duration-700"></div>
    <div class="absolute inset-0 -z-10"></div>

    <!-- Uploader -->
    <div class="mb-3 flex items-center gap-2 text-sm">
        <a href="{{ route('profile.public.show.username', ['username' => $comic->user->name]) }}"
           class="flex items-center gap-2 hover:opacity-80">

            <img
                src="{{ $comic->user->avatar_image_path ? asset($comic->user->avatar_image_path) : asset('default-avatar.png') }}"
                alt="{{ $comic->user->name }} avatar"
                class="h-8 w-8 rounded-full border border-zinc-700 object-cover"
            >

            <span class="font-medium text-zinc-300">
                {{ $comic->user->name }}
            </span>
        </a>
    </div>

    <!-- Comic Cover -->
    <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}"
       title="{{ $comic->title }}"
       class="block">

        <img
            src="{{ asset('storage/' . $comic->image_path) }}"
            alt="{{ $comic->title }}"
            class="mb-3 aspect-[3/4] w-full rounded-lg object-cover transition group-hover:scale-[1.02]"
        >

        <h2 class="mb-1 text-lg font-semibold leading-tight hover:text-indigo-400">
            {{ Str::limit($comic->title, 35) }}
        </h2>
    </a>

    @if (!isset($minified) || !$minified)
        <!-- Author & Date -->
        <p class="text-sm text-zinc-400">
            Por {{ $comic->author }}
        </p>

        <time class="text-xs text-zinc-500">
            {{ $comic->created_at }}
        </time>

        <!-- Description -->
        <p class="mt-2 text-sm text-zinc-400">
            {{ Str::limit($comic->description, 100) }}
        </p>

        <!-- Tags -->
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($comic->tags as $tag)
                <span
                    class="rounded-full bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-300">
                    {{ $tag->name }}
                </span>
            @endforeach
        </div>

        <!-- Page count -->
        <p class="mt-2 text-xs text-zinc-500">
            {{ $comic->pageCount() }} páginas
        </p>
    @endif

    <!-- Engagement: likes + comments (counts degrade to 0 without migrations) -->
    @php
        $cardLiked = in_array($comic->id, $likedComicIds ?? []);
        $cardLikeCount = $comic->liked_by_users_count ?? 0;
        $cardCommentCount = $comic->comments_count ?? 0;
        $cardCollectionCount = $comic->collections_count ?? 0;
    @endphp
    <div class="mt-3 flex items-center justify-center gap-4 text-sm text-zinc-400">
        @auth
            <button type="button"
                    data-comic-like="{{ $comic->id }}"
                    data-like-url="{{ route('comics.like', $comic) }}"
                    data-csrf="{{ csrf_token() }}"
                    data-liked="{{ $cardLiked ? '1' : '0' }}"
                    title="Like"
                    class="inline-flex items-center gap-1 transition {{ $cardLiked ? 'text-rose-500' : 'hover:text-rose-500' }}">
                <svg class="h-[18px] w-[18px]" fill="{{ $cardLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span data-like-count>{{ $cardLikeCount > 0 ? $cardLikeCount : '' }}</span>
            </button>
        @else
            <span class="inline-flex items-center gap-1" title="{{ $cardLikeCount }} likes">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span>{{ $cardLikeCount > 0 ? $cardLikeCount : '' }}</span>
            </span>
        @endauth
        <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}" title="Comments" class="inline-flex items-center gap-1 transition hover:text-sky-400">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8m-8 0a8 8 0 108-8m-8 8V4m0 8l-4-4m4 4l4-4"/>
            </svg>
            <span>{{ $cardCommentCount > 0 ? $cardCommentCount : '' }}</span>
        </a>
        <span class="inline-flex items-center gap-1" title="In {{ $cardCollectionCount }} collection(s)">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span>{{ $cardCollectionCount > 0 ? $cardCollectionCount : '' }}</span>
        </span>
    </div>

    <!-- Share -->
    <div class="mt-4 flex justify-center">
        <button
            onclick="copyToClipboard(this)"
            data-link="{{ url('https://t.me/iv?url=' . (route('comics.showBySlug', ['slug' => $comic->slug])) . '&rhash=7dbb018f868695') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white
                   transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/40">

            ?? Telegram
        </button>
    </div>

</div>
@once
<script>
document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-comic-like]');
    if (!btn) return;
    event.preventDefault();
    event.stopPropagation();
    let res;
    try {
        res = await fetch(btn.dataset.likeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': btn.dataset.csrf, 'Accept': 'application/json' },
        });
    } catch (e) {
        return;
    }
    if (!res.ok) return;
    const data = await res.json();
    const liked = !!data.liked;
    btn.dataset.liked = liked ? '1' : '0';
    btn.classList.toggle('text-rose-500', liked);
    btn.querySelector('svg').setAttribute('fill', liked ? 'currentColor' : 'none');
    btn.querySelector('[data-like-count]').textContent = data.count > 0 ? data.count : '';
});
</script>
@endonce
<script>
function copyToClipboard(button) {
    const link = button.getAttribute('data-link');
    navigator.clipboard.writeText(link)
        .then(() => {
            alert('Link copiado para a área de transferência!');
        })
        .catch(err => {
            console.error('Erro ao copiar o link:', err);
        });
}
</script>
