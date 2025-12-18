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

    <!-- Share -->
    <div class="mt-4 flex justify-center">
        <button
            onclick="copyToClipboard(this)"
            data-link="{{ url('https://t.me/iv?url=' . (route('comics.showBySlug', ['slug' => $comic->slug])) . '&rhash=7dbb018f868695') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white
                   transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/40">

            📋 Telegram
        </button>
    </div>

</div>
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
