<div
    data-comic-card="{{ $comic->id }}"
    data-page-count="{{ $comic->pageCount() }}"
    class="group relative flex flex-col rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow transition duration-300 hover:-translate-y-1 hover:border-indigo-500/60 hover:shadow-xl hover:shadow-indigo-950/40 sm:p-4"
>

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
            alt="{{ $comic->title }} cover"
            loading="lazy"
            class="mb-3 aspect-[3/4] w-full rounded-lg object-cover transition duration-300 group-hover:scale-[1.02]"
        >

        <h2 class="mb-1 text-lg font-semibold leading-tight hover:text-indigo-400">
            {{ Str::limit($comic->title, 35) }}
        </h2>
    </a>

    @if ($comic->isScheduled())
        <p class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-amber-600/20 px-2 py-0.5 text-xs font-medium text-amber-300">
            ⏳ Agendado{{ $comic->published_at ? ' · '.$comic->published_at->format('d/m H:i') : '' }}
        </p>
    @endif

    @if (!isset($minified) || !$minified)
        <!-- Author & Date -->
        <p class="text-sm text-zinc-400">
            Por {{ $comic->author }}
        </p>

        <time class="text-xs text-zinc-500">
            {{ $comic->created_at }}
        </time>

        <!-- Description (desktop only: keeps phone cards compact) -->
        <p class="mt-2 hidden text-sm text-zinc-400 line-clamp-3 sm:block">
            {{ $comic->description }}
        </p>

        <!-- Tags (desktop only) -->
        <div class="mt-3 hidden flex-wrap gap-2 sm:flex">
            @foreach ($comic->tags as $tag)
                @include('components.tag-pill', [
                    'tag' => $tag,
                    'class' => 'rounded-full bg-indigo-500/10 px-2 py-1 text-xs font-medium text-indigo-300',
                ])
            @endforeach
        </div>

    @endif

    <!-- Reading progress (cookie-based, painted by JS; 0% doubles as length indicator) -->
    <div class="mt-3" data-reading-progress>
        <div class="mb-1 flex items-center justify-between text-[11px] font-medium">
            <span data-reading-label class="text-zinc-400"></span>
            <span data-reading-pct class="text-zinc-500"></span>
        </div>
        <div class="h-1 overflow-hidden rounded-full bg-zinc-800" role="progressbar"
             aria-label="Progresso de leitura de {{ $comic->title }}" aria-valuemin="0" aria-valuemax="100" data-reading-bar>
            <div data-reading-fill class="h-full rounded-full bg-indigo-500 transition-all duration-500" style="width: 0%"></div>
        </div>
    </div>

    <!-- Engagement: likes + comments (counts degrade to 0 without migrations) -->
    @php
        $cardLiked = in_array($comic->id, $likedComicIds);
        $cardLikeCount = $comic->liked_by_users_count ?? 0;
        $cardCommentCount = $comic->comments_count ?? 0;
        $cardCollectionCount = $comic->collections_count ?? 0;
    @endphp
    <div class="mt-3 flex items-center justify-center gap-2 text-sm text-zinc-400 sm:gap-4">
        @auth
            <button type="button"
                    data-comic-like="{{ $comic->id }}"
                    data-like-url="{{ route('comics.like', $comic) }}"
                    data-csrf="{{ csrf_token() }}"
                    data-liked="{{ $cardLiked ? '1' : '0' }}"
                    aria-label="{{ $cardLiked ? 'Descurtir' : 'Curtir' }} {{ $comic->title }}"
                    aria-pressed="{{ $cardLiked ? 'true' : 'false' }}"
                    class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1 rounded-lg transition {{ $cardLiked ? 'text-rose-500' : 'hover:text-rose-500 active:bg-zinc-800' }}">
                <svg class="h-[18px] w-[18px]" fill="{{ $cardLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span data-like-count>{{ $cardLikeCount > 0 ? $cardLikeCount : '' }}</span>
            </button>
        @else
            <span class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1" title="{{ $cardLikeCount }} likes">
                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                <span>{{ $cardLikeCount > 0 ? $cardLikeCount : '' }}</span>
            </span>
        @endauth
        <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}" title="Comentários" aria-label="Comentários em {{ $comic->title }}" class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center gap-1 rounded-lg transition hover:text-sky-400 active:bg-zinc-800">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8m-8 0a8 8 0 108-8m-8 8V4m0 8l-4-4m4 4l4-4"/>
            </svg>
            <span>{{ $cardCommentCount > 0 ? $cardCommentCount : '' }}</span>
        </a>
        <span class="inline-flex items-center gap-1" title="Em {{ $cardCollectionCount }} coleção(ões)">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <span>{{ $cardCollectionCount > 0 ? $cardCollectionCount : '' }}</span>
        </span>
    </div>

    <!-- Share -->
    <div class="mt-4 flex justify-center">
        <button
            type="button"
            onclick="copyToClipboard(this)"
            data-link="{{ url('https://t.me/iv?url=' . (route('comics.showBySlug', ['slug' => $comic->slug])) . '&rhash=7dbb018f868695') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white
                   transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/40">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21.9 4.6L2.7 12.1c-.8.3-.8 1.4.1 1.6l4.7 1.5 1.8 5.6c.3.8 1.3.9 1.8.2l2.6-3.1 4.9 3.6c.6.4 1.5.1 1.7-.6l2.6-14.6c.2-.9-.6-1.7-1 0zM8.4 13.3l9.5-6.2c.2-.1.4.2.2.3l-7.9 7.2-.3 3-1.5-4.3z"/>
            </svg>
            <span>Compartilhar</span>
        </button>
    </div>

</div>
@once
<script>
// Reading progress: each card carries its comic id + page count; the
// reader stores the current page in a `comic_{id}_page` cookie.
function readingProgressCookie(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function paintReadingProgress() {
    document.querySelectorAll('[data-comic-card]').forEach(function (card) {
        var total = parseInt(card.getAttribute('data-page-count'), 10) || 0;
        if (total <= 0) return;
        var wrap = card.querySelector('[data-reading-progress]');
        if (!wrap) return;
        var bar = wrap.querySelector('[data-reading-bar]');
        var label = wrap.querySelector('[data-reading-label]');
        var pctEl = wrap.querySelector('[data-reading-pct]');
        var fill = wrap.querySelector('[data-reading-fill]');

        var stored = parseInt(readingProgressCookie('comic_' + card.getAttribute('data-comic-card') + '_page'), 10) || 0;
        if (stored <= 0) {
            // Never opened: the empty bar + total still communicates length.
            label.textContent = 'Não iniciado · ' + total + ' páginas';
            pctEl.textContent = '0%';
            fill.style.width = '0%';
            bar.setAttribute('aria-valuenow', '0');
            return;
        }

        var done = Math.min(stored, total);
        var pct = Math.round((done / total) * 100);

        var completed = stored >= total;
        label.textContent = completed ? 'Concluído · ' + total + ' páginas' : 'Página ' + done + ' de ' + total;
        pctEl.textContent = pct + '%';
        fill.style.width = pct + '%';
        if (completed) {
            fill.classList.remove('bg-indigo-500');
            fill.classList.add('bg-emerald-500');
        }
        bar.setAttribute('aria-valuenow', String(pct));
    });
}

document.addEventListener('DOMContentLoaded', paintReadingProgress);
</script>
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
    const title = btn.getAttribute('aria-label') || '';
    btn.setAttribute('aria-label', title.replace(/^(Curtir|Descurtir)/, liked ? 'Descurtir' : 'Curtir'));
    btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
});
</script>
@endonce
<script>
function copyToClipboard(button) {
    const link = button.getAttribute('data-link');
    const label = button.querySelector('span');
    const original = label ? label.textContent : 'Compartilhar';
    const done = function () {
        if (!label) return;
        label.textContent = 'Copiado!';
        setTimeout(function () { label.textContent = original; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(done).catch(function (err) {
            console.error('Falha ao copiar:', err);
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = link;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (err) { console.error('Falha ao copiar:', err); }
        document.body.removeChild(ta);
    }
}
</script>
