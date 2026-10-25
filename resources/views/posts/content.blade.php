@php
    $author = $post->author;
    $authorName = $author?->name ?? 'Usuário removido';
    $handle = $author ? '@' . $author->handleName() : '@desconhecido';
    $authorUrl = $author ? route('profile.public.show.username', ['username' => $author->handleName()]) : null;
    $avatar = $author?->avatar_image_path
        ? asset($author->avatar_image_path)
        : Vite::asset('resources/images/default-avatar.svg');
    // $post->media may be an array (new rows, via casts) or a JSON string (legacy rows)
    $mediaFiles = is_array($post->media) ? $post->media : (json_decode($post->media, true) ?: []);
    $quoted = $post->referencedPost;
    $quotedAuthor = $quoted?->author;
@endphp

<div class="flex gap-3">
    {{-- Avatar --}}
    <div class="shrink-0">
        @if ($author)
            <a href="{{ $authorUrl }}"
               data-hovercard="{{ $author->name }}">
                <img src="{{ $avatar }}" alt="{{ $authorName }} avatar"
                     class="h-10 w-10 rounded-full border border-zinc-800 object-cover hover:opacity-80">
            </a>
        @else
            <img src="{{ $avatar }}" alt="Avatar de usuário removido"
                 class="h-10 w-10 rounded-full border border-zinc-800 object-cover opacity-60">
        @endif
    </div>

    <div class="min-w-0 flex-1">
        {{-- Header: Name @handle · time --}}
        <div class="flex items-center gap-1 text-sm">
            @if ($author)
                <a href="{{ $authorUrl }}"
                   data-hovercard="{{ $author->name }}"
                   class="truncate font-bold text-zinc-100 hover:underline">
                    {{ $authorName }}
                </a>
                <span class="truncate text-zinc-500">{{ $handle }}</span>
                @include('components.user-level', ['user' => $author])
            @else
                <span class="truncate font-bold text-zinc-500">{{ $authorName }}</span>
            @endif
            <span class="text-zinc-600">·</span>
            <a href="{{ route('posts.show', $post) }}"
               class="shrink-0 text-zinc-500 transition hover:text-zinc-300 hover:underline"
               title="{{ $post->created_at }}">
                {{ $post->created_at?->diffForHumans() }}
            </a>
            @if ($post->edited_at)
                <span class="shrink-0 text-xs text-zinc-600" title="Editado {{ $post->edited_at }}">· editado</span>
            @endif
        </div>

        {{-- Text (links + mentions render as anchors) --}}
        @if (!empty($post->text))
            <div class="mt-0.5 text-[15px] leading-normal text-zinc-100 whitespace-pre-line break-words">
                {!! \App\Support\PostText::render($post->text) !!}
            </div>
        @endif

        {{-- Link preview (YouTube / comic / generic card) --}}
        @include('posts.preview', ['preview' => \App\Support\LinkPreview::forText($post->text)])

        {{-- Media --}}
        @if (!empty($mediaFiles))
            <div class="mt-3 overflow-hidden rounded-2xl border border-zinc-800">
                @if (count($mediaFiles) === 1)
                    <button type="button"
                            data-post-image-view
                            data-image-src="{{ asset($mediaFiles[0]) }}"
                            data-image-alt="Imagem do post de {{ $authorName }}"
                            aria-label="Ampliar imagem do post"
                            class="flex w-full cursor-zoom-in justify-center bg-zinc-950/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">
                        <img src="{{ asset($mediaFiles[0]) }}" alt="Imagem do post de {{ $authorName }}"
                             class="block h-auto max-h-[32rem] max-w-full object-contain">
                    </button>
                @else
                    <div class="grid grid-cols-2 gap-0.5">
                        @foreach (array_slice($mediaFiles, 0, 4) as $media)
                            <button type="button"
                                    data-post-image-view
                                    data-image-src="{{ asset($media) }}"
                                    data-image-alt="Imagem {{ $loop->iteration }} do post de {{ $authorName }}"
                                    aria-label="Ampliar imagem {{ $loop->iteration }} do post"
                                    class="flex aspect-square w-full cursor-zoom-in items-center justify-center overflow-hidden bg-zinc-950/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">
                                <img src="{{ asset($media) }}" alt="Imagem {{ $loop->iteration }} do post de {{ $authorName }}"
                                     class="h-full w-full object-contain">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Quoted post --}}
        @if ($quoted)
            <div class="mt-3 rounded-2xl border border-zinc-800 p-3 transition hover:bg-zinc-900/40">
                <div class="flex items-center gap-1 text-sm">
                    <img src="{{ $quotedAuthor?->avatar_image_path ? asset($quotedAuthor->avatar_image_path) : Vite::asset('resources/images/default-avatar.svg') }}"
                         alt="Avatar de {{ $quotedAuthor?->name ?? 'usuário removido' }}" class="h-5 w-5 rounded-full object-cover">
                    <span class="truncate font-bold text-zinc-200">
                        {{ $quotedAuthor?->name ?? 'Usuário removido' }}
                    </span>
                    <span class="text-zinc-500">·</span>
                    <span class="text-zinc-500">{{ $quoted->created_at?->diffForHumans() }}</span>
                </div>
                @if (!empty($quoted->text))
                    <div class="mt-1 text-sm text-zinc-300 whitespace-pre-line break-words">
                        {!! \App\Support\PostText::render($quoted->text) !!}
                    </div>
                @endif
            </div>
        @endif

        {{-- Action bar --}}
        <div class="mt-2 flex max-w-md items-center justify-between text-zinc-500">
            @php
                $isLiked = in_array($post->id, $likedPostIds ?? []);
                $likeCount = $post->liked_by_users_count ?? 0;
            @endphp
            {{-- Like: toggles without reload --}}
            @once
                <script>
                    window.togglePostLike = async function (url, btn) {
                        let res;
                        try {
                            res = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                },
                            });
                        } catch (e) {
                            return;
                        }
                        if (!res.ok) return;
                        const data = await res.json();
                        const liked = !!data.liked;
                        btn.dataset.liked = liked ? '1' : '0';
                        btn.classList.toggle('text-rose-500', liked);
                        btn.classList.toggle('hover:text-rose-500', !liked);
                        btn.querySelector('.like-heart').setAttribute('fill', liked ? 'currentColor' : 'none');
                        btn.querySelector('.like-count').textContent = data.count > 0 ? data.count : '';
                        if (window.openXpToast) window.openXpToast(data.xp);
                    };
                    window.togglePostBookmark = async function (url, btn) {
                        let res;
                        try {
                            res = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json',
                                },
                            });
                        } catch (e) {
                            return;
                        }
                        if (!res.ok) return;
                        const data = await res.json();
                        const on = !!data.bookmarked;
                        btn.dataset.bookmarked = on ? '1' : '0';
                        btn.classList.toggle('text-amber-400', on);
                        btn.classList.toggle('hover:text-amber-400', !on);
                        const icon = btn.querySelector('.bookmark-icon');
                        if (icon) icon.setAttribute('fill', on ? 'currentColor' : 'none');
                    };
                </script>
            @endonce
            @auth
                <button type="button"
                        onclick="togglePostLike('{{ route('posts.like', $post) }}', this)"
                        title="Curtir"
                        data-liked="{{ $isLiked ? '1' : '0' }}"
                        class="group flex items-center gap-1 text-xs transition {{ $isLiked ? 'text-rose-500' : 'hover:text-rose-500' }}">
                    <span class="rounded-full p-2 transition group-hover:bg-rose-500/10">
                        <svg class="h-[18px] w-[18px] like-heart" fill="{{ $isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </span>
                    <span class="like-count">{{ $likeCount > 0 ? $likeCount : '' }}</span>
                </button>
            @else
                <span class="flex items-center gap-1 text-xs" title="{{ $likeCount }} likes">
                    <span class="rounded-full p-2">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </span>
                    <span>{{ $likeCount > 0 ? $likeCount : '' }}</span>
                </span>
            @endauth

            {{-- Bookmark: private, saved for later (auth only) --}}
            @auth
                @php($isBookmarked = in_array($post->id, $bookmarkedPostIds ?? []))
                <button type="button"
                        onclick="togglePostBookmark('{{ route('posts.bookmark', $post) }}', this)"
                        title="Salvar"
                        data-bookmarked="{{ $isBookmarked ? '1' : '0' }}"
                        class="group flex items-center gap-1 text-xs transition {{ $isBookmarked ? 'text-amber-400' : 'hover:text-amber-400' }}">
                    <span class="rounded-full p-2 transition group-hover:bg-amber-500/10">
                        <svg class="h-[18px] w-[18px] bookmark-icon" fill="{{ $isBookmarked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                        </svg>
                    </span>
                </button>
            @endauth

            {{-- Reply: prefills the composer (id lets thread pages target the node) --}}
            <button type="button"
                    onclick="replyToPost('{{ addslashes($handle) }}', {{ $post->id }})"
                    title="Responder"
                    class="group flex items-center gap-1 text-xs transition hover:text-sky-500">
                <span class="rounded-full p-2 transition group-hover:bg-sky-500/10">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8m-8 0a8 8 0 108-8m-8 8V4m0 8l-4-4m4 4l4-4"/>
                    </svg>
                </span>
            </button>
            {{-- Reply count opens the thread --}}
            <a href="{{ route('posts.show', $post) }}"
               title="Abrir thread"
               class="text-xs transition hover:text-sky-500">
                @if (($post->replies_count ?? 0) > 0)
                    <span>{{ $post->replies_count }}</span>
                @endif
            </a>

            {{-- Repost (quote): prefills the composer with a quote --}}
            <button type="button"
                    onclick="quotePost({{ $post->id }}, '{{ addslashes($handle) }}', '{{ addslashes(Str::limit($post->text ?? '', 80)) }}')"
                    title="Republicar"
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

            {{-- Views: counted once per viewer on the thread page --}}
            <span class="flex items-center gap-1 text-xs" title="{{ $post->view_count ?? 0 }} visualizações">
                <span class="rounded-full p-2">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                @if (($post->view_count ?? 0) > 0)
                    <span>{{ $post->view_count }}</span>
                @endif
            </span>

            {{-- Share: copies the post permalink --}}
            <button type="button"
                    onclick="sharePost(this, '{{ route('posts.show', $post) }}')"
                    title="Copiar link"
                    class="group flex items-center gap-1 text-xs transition hover:text-indigo-500">
                <span class="rounded-full p-2 transition group-hover:bg-indigo-500/10">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13"/>
                    </svg>
                </span>
                <span class="share-label hidden">Copiado!</span>
            </button>

            {{-- Edit: owner or admin --}}
            @if (auth()->user()?->can('update', $post))
                <a href="{{ route('posts.edit', $post) }}"
                   title="Editar post"
                   aria-label="Editar post"
                   class="group flex items-center gap-1 text-xs transition hover:text-amber-400">
                    <span class="rounded-full p-2 transition group-hover:bg-amber-500/10">
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/>
                        </svg>
                    </span>
                </a>
            @endif

            {{-- Delete: owner or admin (replies/quotes cascade) --}}
            @can('delete', $post)
                <form method="POST" action="{{ route('posts.destroy', $post) }}"
                      onsubmit="return confirm('Excluir este post? As respostas e citações também serão removidas.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" title="Excluir post" aria-label="Excluir post"
                            class="group flex items-center gap-1 text-xs transition hover:text-rose-400">
                        <span class="rounded-full p-2 transition group-hover:bg-rose-500/10">
                            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-8 0 1 13h8l1-13"/>
                            </svg>
                        </span>
                    </button>
                </form>
            @endcan

            {{-- Report: anyone but the author --}}
            @auth
                @if (! auth()->user()->is($author))
                    <div x-data="{ reportOpen: false }" class="relative">
                        <button type="button" @click="reportOpen = !reportOpen" title="Denunciar"
                                class="group flex items-center gap-1 text-xs transition hover:text-rose-400">
                            <span class="rounded-full p-2 transition group-hover:bg-rose-500/10">
                                <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18M3 4.5h13l-2.25 3.75L16 12H3"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="reportOpen" @click.outside="reportOpen = false" x-cloak
                             class="absolute bottom-9 right-0 z-30 w-60 rounded-xl border border-zinc-700 bg-zinc-900 p-3 shadow-2xl">
                            <form method="POST" action="{{ route('posts.report', $post) }}">
                                @csrf
                                <label class="mb-1 block text-xs text-zinc-400">Motivo</label>
                                <select name="reason" class="mb-2 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-2 py-1.5 text-sm">
                                    @foreach (\App\Models\Report::reasons() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <textarea name="details" rows="2" maxlength="1000" placeholder="Detalhes (opcional)"
                                          class="mb-2 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-2 py-1.5 text-sm"></textarea>
                                <button type="submit" class="w-full rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-rose-500">
                                    Denunciar
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @endauth
        </div>
    </div>
</div>

@once
    <dialog id="post-image-modal"
            class="fixed inset-0 m-auto max-h-[90dvh] max-w-[96vw] overflow-visible border-0 bg-transparent p-0 text-white backdrop:bg-black/90">
        <div class="relative flex max-h-[90dvh] max-w-[96vw] items-center justify-center">
            <button type="button" id="post-image-modal-close"
                    class="absolute -right-2 -top-2 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-zinc-900 text-2xl text-white shadow-lg transition hover:bg-zinc-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Fechar imagem ampliada">
                ×
            </button>
            <img id="post-image-modal-content" src="" alt=""
                 class="max-h-[85dvh] max-w-[96vw] rounded-lg object-contain shadow-2xl">
        </div>
    </dialog>

    <script>
        const postImageModal = document.getElementById('post-image-modal');
        const postImageModalContent = document.getElementById('post-image-modal-content');

        document.addEventListener('click', function (event) {
            const imageButton = event.target.closest('[data-post-image-view]');
            if (imageButton) {
                postImageModalContent.src = imageButton.dataset.imageSrc;
                postImageModalContent.alt = imageButton.dataset.imageAlt;
                postImageModal.showModal();
            }
        });

        document.getElementById('post-image-modal-close').addEventListener('click', function () {
            postImageModal.close();
        });

        postImageModal.addEventListener('click', function (event) {
            event.stopPropagation();
            if (event.target === postImageModal) {
                postImageModal.close();
            }
        });

        // Whole-card navigation: clicking a post opens its thread, except
        // on interactive elements (buttons, links, inputs), text selections,
        // modifier-clicks (kept as new-tab), and the already-open thread.
        document.addEventListener('click', function (event) {
            const card = event.target.closest('[data-thread-url]');
            if (!card) return;
            if (event.target.closest('a, button, input, textarea, select, form, label')) return;
            const selection = window.getSelection();
            if (selection && selection.toString().length > 0) return;
            const url = card.getAttribute('data-thread-url');
            if (!url || url === window.location.href) return;
            if (event.ctrlKey || event.metaKey || event.button === 1) {
                window.open(url, '_blank', 'noopener');
                return;
            }
            window.location.href = url;
        });
    </script>
@endonce
