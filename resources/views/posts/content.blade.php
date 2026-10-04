@php
    $author = $post->author;
    $authorName = $author?->name ?? 'Deleted user';
    $handle = $author ? '@' . Str::slug($author->name, '') : '@unknown';
    $avatar = $author?->avatar_image_path
        ? asset($author->avatar_image_path)
        : asset('default-avatar.png');
    // $post->media may be an array (new rows, via casts) or a JSON string (legacy rows)
    $mediaFiles = is_array($post->media) ? $post->media : (json_decode($post->media, true) ?: []);
    $quoted = $post->referencedPost;
    $quotedAuthor = $quoted?->author;
@endphp

<div class="flex gap-3">
    {{-- Avatar --}}
    <div class="shrink-0">
        @if ($author)
            <a href="{{ route('profile.public.show.username', ['username' => $author->name]) }}">
                <img src="{{ $avatar }}" alt="{{ $authorName }} avatar"
                     class="h-10 w-10 rounded-full border border-zinc-800 object-cover hover:opacity-80">
            </a>
        @else
            <img src="{{ $avatar }}" alt="Deleted user avatar"
                 class="h-10 w-10 rounded-full border border-zinc-800 object-cover opacity-60">
        @endif
    </div>

    <div class="min-w-0 flex-1">
        {{-- Header: Name @handle · time --}}
        <div class="flex items-center gap-1 text-sm">
            @if ($author)
                <a href="{{ route('profile.public.show.username', ['username' => $author->name]) }}"
                   class="truncate font-bold text-zinc-100 hover:underline">
                    {{ $authorName }}
                </a>
                <span class="truncate text-zinc-500">{{ $handle }}</span>
            @else
                <span class="truncate font-bold text-zinc-500">{{ $authorName }}</span>
            @endif
            <span class="text-zinc-600">·</span>
            <span class="shrink-0 text-zinc-500" title="{{ $post->created_at }}">
                {{ $post->created_at?->diffForHumans() }}
            </span>
        </div>

        {{-- Text --}}
        @if (!empty($post->text))
            <p class="mt-0.5 text-[15px] leading-normal text-zinc-100 whitespace-pre-line break-words">
                {{ $post->text }}
            </p>
        @endif

        {{-- Media --}}
        @if (!empty($mediaFiles))
            <div class="mt-3 overflow-hidden rounded-2xl border border-zinc-800">
                @if (count($mediaFiles) === 1)
                    <img src="{{ asset($mediaFiles[0]) }}" alt="Post media"
                         class="max-h-96 w-full object-cover">
                @else
                    <div class="grid grid-cols-2 gap-0.5">
                        @foreach (array_slice($mediaFiles, 0, 4) as $media)
                            <img src="{{ asset($media) }}" alt="Post media"
                                 class="h-48 w-full object-cover">
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Quoted post --}}
        @if ($quoted)
            <div class="mt-3 rounded-2xl border border-zinc-800 p-3 transition hover:bg-zinc-900/40">
                <div class="flex items-center gap-1 text-sm">
                    <img src="{{ $quotedAuthor?->avatar_image_path ? asset($quotedAuthor->avatar_image_path) : asset('default-avatar.png') }}"
                         alt="avatar" class="h-5 w-5 rounded-full object-cover">
                    <span class="truncate font-bold text-zinc-200">
                        {{ $quotedAuthor?->name ?? 'Deleted user' }}
                    </span>
                    <span class="text-zinc-500">·</span>
                    <span class="text-zinc-500">{{ $quoted->created_at?->diffForHumans() }}</span>
                </div>
                @if (!empty($quoted->text))
                    <p class="mt-1 text-sm text-zinc-300 whitespace-pre-line break-words">
                        {{ $quoted->text }}
                    </p>
                @endif
            </div>
        @endif

        {{-- Action bar --}}
        <div class="mt-2 flex max-w-md items-center justify-between text-zinc-500">
            {{-- Reply: prefills the composer --}}
            <button type="button"
                    onclick="replyToPost('{{ addslashes($handle) }}')"
                    title="Reply"
                    class="group flex items-center gap-1 text-xs transition hover:text-sky-500">
                <span class="rounded-full p-2 transition group-hover:bg-sky-500/10">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8m-8 0a8 8 0 108-8m-8 8V4m0 8l-4-4m4 4l4-4"/>
                    </svg>
                </span>
            </button>
            {{-- Reply count opens the thread --}}
            <a href="{{ route('posts.show', $post) }}"
               title="Open thread"
               class="text-xs transition hover:text-sky-500">
                @if (($post->replies_count ?? 0) > 0)
                    <span>{{ $post->replies_count }}</span>
                @endif
            </a>

            {{-- Repost (quote): prefills the composer with a quote --}}
            <button type="button"
                    onclick="quotePost({{ $post->id }}, '{{ addslashes($handle) }}', '{{ addslashes(Str::limit($post->text ?? '', 80)) }}')"
                    title="Repost"
                    class="group flex items-center gap-1 text-xs transition hover:text-emerald-500">
                <span class="rounded-full p-2 transition group-hover:bg-emerald-500/10">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 1l4 4-4 4M3 11V9a4 4 0 014-4h14M7 23l-4-4 4-4M21 13v2a4 4 0 01-4 4H3"/>
                    </svg>
                </span>
                @if (($post->quotes_count ?? 0) > 0)
                    <span>{{ $post->quotes_count }}</span>
                @endif
            </button>

            {{-- Share: copies the post permalink --}}
            <button type="button"
                    onclick="sharePost(this, '{{ route('posts.show', $post) }}')"
                    title="Copy link"
                    class="group flex items-center gap-1 text-xs transition hover:text-indigo-500">
                <span class="rounded-full p-2 transition group-hover:bg-indigo-500/10">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/>
                    </svg>
                </span>
                <span class="share-label hidden">Copied!</span>
            </button>
        </div>
    </div>
</div>
