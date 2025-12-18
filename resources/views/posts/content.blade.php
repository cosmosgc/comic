<!-- Post header -->
<div class="mb-3 flex items-center gap-3">
    <a href="{{ route('profile.public.show.username', ['username' => $post->author->name]) }}"
       class="flex items-center gap-2 hover:opacity-80">

        <img
            src="{{ $post->author->avatar_image_path ? asset($post->author->avatar_image_path) : asset('default-avatar.png') }}"
            alt="{{ $post->author->name }} avatar"
            class="h-9 w-9 rounded-full border border-zinc-700 object-cover"
        >

        <span class="text-sm font-medium text-zinc-300">
            {{ $post->author->name }}
        </span>
    </a>
</div>

<!-- Post text -->
<p class="mb-3 text-sm text-zinc-300 whitespace-pre-line">
    {{ $post->text }}
</p>

<!-- Media -->
@if (!empty($post->media))
    @php
        $mediaFiles = json_decode($post->media, true);
    @endphp

    @if (is_array($mediaFiles))
        <div class="mb-3 grid gap-2 sm:grid-cols-2">
            @foreach ($mediaFiles as $media)
                <img
                    src="{{ asset($media) }}"
                    alt="Post media"
                    class="rounded-lg border border-zinc-800 object-cover"
                >
            @endforeach
        </div>
    @endif
@endif

<!-- Referenced post -->
@if ($post->referencedPost)
    <blockquote class="mt-3 rounded-lg border-l-4 border-indigo-500 bg-zinc-800/60 p-3 text-sm text-zinc-300">
        <p class="mb-1">
            {{ $post->referencedPost->text }}
        </p>
        <footer class="text-xs text-zinc-400">
            — {{ $post->referencedPost->username }}
        </footer>
    </blockquote>
@endif
