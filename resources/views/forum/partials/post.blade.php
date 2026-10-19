@php
    $author = $post->author;
    $avatar = $author?->avatar_image_path ? asset($author->avatar_image_path) : Vite::asset('resources/images/default-avatar.svg');
    $active = $viewerReactions[$post->id] ?? [];
    $isMod = $canModerate ?? false;
    $isOwner = auth()->check() && (int) $post->user_id === (int) auth()->id();
    $isThreadOwner = auth()->check() && (int) $thread->user_id === (int) auth()->id();
@endphp
<article id="post-{{ $post->id }}" class="px-4 py-4">
    <div class="flex gap-3">
        <img src="{{ $avatar }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <a href="{{ route('profile.public.show.id', $post->user_id) }}" data-hovercard="{{ $author?->name }}" class="font-bold hover:underline">{{ $author?->name ?? 'Usuário removido' }}</a>
                @if ($author)
                    <span class="text-xs text-zinc-500">{{ '@'.$author->handleName() }}</span>
                    @if ($badge = $author->topBadge())
                        <span class="text-xs" title="{{ $badge->label }}">{{ $badge->icon }}</span>
                    @endif
                @endif
                @if ($post->isOpening())
                    <span class="rounded bg-indigo-500/20 px-1.5 py-0.5 text-[11px] font-bold text-indigo-300">OP</span>
                @endif
                @if ($thread->solved_post_id === $post->id)
                    <span class="rounded bg-emerald-500/20 px-1.5 py-0.5 text-[11px] font-bold text-emerald-300">Solução</span>
                @endif
                <span class="text-xs text-zinc-500">· {{ $post->created_at->diffForHumans() }}</span>
                @if ($post->isEdited())
                    <span class="text-xs text-zinc-500" title="Editado {{ $post->edited_at->diffForHumans() }}">· editado</span>
                @endif
                @unless ($post->is_approved)
                    <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[11px] font-bold text-amber-300">aguardando aprovação</span>
                @endunless
            </div>

            @if ($post->parent && $post->parent->author)
                <blockquote class="mt-2 border-l-2 border-zinc-700 pl-3 text-xs text-zinc-400">
                    <span class="font-semibold text-zinc-300">{{ $post->parent->author->name }}</span>:
                    {{ \Illuminate\Support\Str::limit(strip_tags(\App\Support\Markdown::toHtml($post->parent->body)), 120) }}
                </blockquote>
            @endif

            <div class="prose-forum mt-2 break-words text-sm leading-relaxed text-zinc-200">
                {!! \App\Support\Markdown::toHtml($post->body) !!}
            </div>

            @if ($post->attachments->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($post->attachments as $attachment)
                        @if ($attachment->isImage())
                            <a href="{{ $attachment->url() }}" target="_blank" rel="noopener">
                                <img src="{{ $attachment->url() }}" alt="{{ $attachment->original_name }}" class="max-h-40 rounded-lg border border-zinc-800 object-cover">
                            </a>
                        @else
                            <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="rounded-lg border border-zinc-800 px-2 py-1 text-xs text-sky-400 hover:underline">{{ $attachment->original_name }}</a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                @foreach (\App\Models\ForumPostReaction::kinds() as $kind => $emoji)
                    @php($count = $post->reactions->where('reaction', $kind)->count())
                    @auth
                        <button type="button" data-reaction="{{ $kind }}"
                                data-url="{{ route('forum.posts.react', $post) }}"
                                @class(['forum-react rounded-full border px-2 py-0.5 transition', 'border-indigo-500 bg-indigo-500/10 text-indigo-300' => in_array($kind, $active, true), 'border-zinc-700 text-zinc-300 hover:bg-zinc-800' => ! in_array($kind, $active, true)])
                                onclick="forumReact(this)">
                            {{ $emoji }} <span class="forum-react-count">{{ $count }}</span>
                        </button>
                    @else
                        <span class="rounded-full border border-zinc-800 px-2 py-0.5 text-zinc-400">{{ $emoji }} {{ $count }}</span>
                    @endauth
                @endforeach

                @auth
                    <button type="button" onclick="document.getElementById('reply-{{ $post->id }}').classList.toggle('hidden')" class="text-zinc-400 hover:text-zinc-100">Responder</button>
                @endauth
                @if ($thread->isQuestion() && ! $post->isOpening() && ($isThreadOwner || $isMod))
                    <form method="POST" action="{{ route('forum.threads.solve', $post) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-emerald-400 hover:text-emerald-300">{{ $thread->solved_post_id === $post->id ? 'Desmarcar solução' : 'Marcar solução' }}</button>
                    </form>
                @endif
                @if ($isMod)
                    <form method="POST" action="{{ route('forum.posts.approve', $post) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-amber-400 hover:text-amber-300">{{ $post->is_approved ? 'Reprovar' : 'Aprovar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('forum.posts.destroy', $post) }}" class="inline" onsubmit="return confirm('Remover este post?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-rose-400 hover:text-rose-300">Remover (mod)</button>
                    </form>
                @endif
                @auth
                    @unless ($isOwner)
                        <button type="button" onclick="document.getElementById('report-post-{{ $post->id }}').classList.toggle('hidden')" class="text-zinc-500 hover:text-rose-300">Denunciar</button>
                    @endunless
                @endauth
            </div>

            @auth
                <form id="reply-{{ $post->id }}" class="mt-2 hidden" method="POST" action="{{ route('forum.posts.store', $thread) }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $post->id }}">
                    <textarea name="body" rows="2" required class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Responder a {{ $author?->name }}…"></textarea>
                    <button type="submit" class="mt-1 rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-500">Responder</button>
                </form>

                @unless ($isOwner)
                    <form id="report-post-{{ $post->id }}" class="mt-2 hidden rounded-lg border border-zinc-800 p-3" method="POST" action="{{ route('forum.posts.report', $post) }}">
                        @csrf
                        <div class="flex flex-wrap items-end gap-2">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-zinc-400">Motivo</label>
                                <select name="reason" class="rounded-lg border border-zinc-800 bg-zinc-900 px-2 py-1 text-xs focus:outline-none">
                                    @foreach (\App\Models\ForumReport::reasons() as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-[12rem] flex-1">
                                <label class="mb-1 block text-[11px] font-semibold text-zinc-400">Detalhes</label>
                                <input name="details" maxlength="1000" class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-2 py-1 text-xs focus:outline-none">
                            </div>
                            <button type="submit" class="rounded-full bg-rose-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-rose-500">Enviar</button>
                        </div>
                    </form>
                @endunless
            @endauth
        </div>
    </div>
</article>
