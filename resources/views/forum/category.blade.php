@extends('layouts.forum-layout')

@section('title', $category->name.' — Fórum')

@section('content')
<div class="mx-auto min-h-screen max-w-4xl px-4 py-6">

    <nav class="mb-3 text-xs text-zinc-500" aria-label="Trilha">
        <a href="{{ route('forum.index') }}" class="hover:text-zinc-300">Fórum</a>
        <span aria-hidden="true"> › </span>
        <span>{{ $category->name }}</span>
    </nav>

    @php($bannerUrl = $category->bannerUrl())

    @if ($bannerUrl)
        {{-- Board hero: bigger and bolder than the thread banner. --}}
        <header class="relative mb-6 overflow-hidden rounded-3xl border border-zinc-800 bg-zinc-900 shadow-2xl shadow-indigo-950/30">
            <img src="{{ $bannerUrl }}" alt="Banner de {{ $category->name }}"
                 class="absolute inset-0 h-full w-full object-cover"
                 style="object-position: {{ $category->bannerPosition() }}; mask-image: linear-gradient(to right, #000 0%, #000 65%, transparent 100%); -webkit-mask-image: linear-gradient(to right, #000 0%, #000 65%, transparent 100%);">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-zinc-950/95 via-zinc-950/55 to-zinc-950/10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-zinc-950/90 to-transparent" aria-hidden="true"></div>
            <div class="relative flex min-h-44 flex-col justify-end px-6 py-7 sm:min-h-60 sm:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-zinc-950/70 text-3xl shadow-lg backdrop-blur sm:h-16 sm:w-16" aria-hidden="true">
                            {{ $category->icon ?: '💬' }}
                        </span>
                        <div>
                            <h1 class="text-3xl font-extrabold tracking-tight drop-shadow sm:text-4xl">{{ $category->name }}</h1>
                            @if ($category->description)
                                <p class="mt-1 max-w-2xl text-sm text-zinc-300">{{ $category->description }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($canStartThread)
                        <button type="button" onclick="document.getElementById('new-thread').classList.toggle('hidden')"
                                class="rounded-full bg-indigo-500 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-950/50 transition hover:bg-indigo-400">
                            Novo tópico
                        </button>
                    @endif
                </div>
            </div>
        </header>
    @else
        <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">{{ $category->icon ? $category->icon.' ' : '' }}{{ $category->name }}</h1>
                @if ($category->description)
                    <p class="text-sm text-zinc-400">{{ $category->description }}</p>
                @endif
            </div>
            @if ($canStartThread)
                <button type="button" onclick="document.getElementById('new-thread').classList.toggle('hidden')"
                        class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-indigo-500">
                    Novo tópico
                </button>
            @endif
        </header>
    @endif

    @if ($canStartThread)
        {{-- Keep the form open after a failed POST so the validation error is visible. --}}
        <div id="new-thread" @class(['mb-6 rounded-xl border border-zinc-800 p-4', 'hidden' => ! $errors->any()])>
            <form method="POST" action="{{ route('forum.threads.store', ['categorySlug' => $category->slug]) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label for="thread-title" class="mb-1 block text-xs font-semibold text-zinc-400">Título</label>
                    <input id="thread-title" name="title" required minlength="3" maxlength="150" value="{{ old('title') }}"
                           class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="flex-1">
                        <label for="thread-type" class="mb-1 block text-xs font-semibold text-zinc-400">Tipo</label>
                        <select id="thread-type" name="type" class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:outline-none">
                            <option value="discussion" @selected(old('type', 'discussion') === 'discussion')>Discussão</option>
                            <option value="question" @selected(old('type') === 'question')>Pergunta</option>
                            <option value="poll" @selected(old('type') === 'poll')>Enquete</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label for="thread-tags" class="mb-1 block text-xs font-semibold text-zinc-400">Tags (separadas por vírgula)</label>
                        <input id="thread-tags" name="tags[]" class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:outline-none" placeholder="ajuda, teorias">
                    </div>
                </div>
                <div>
                    <label for="thread-body" class="mb-1 block text-xs font-semibold text-zinc-400">Mensagem (Markdown)</label>
                    <textarea id="thread-body" name="body" required rows="6" class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">{{ old('body') }}</textarea>
                </div>
                <div id="poll-fields" class="{{ old('type') === 'poll' ? '' : 'hidden' }} rounded-lg border border-zinc-800 p-3">
                    <p class="mb-2 text-xs font-semibold text-zinc-400">Enquete</p>
                    <input name="poll_question" value="{{ old('poll_question') }}" maxlength="200" placeholder="Pergunta da enquete"
                           class="mb-2 w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <div class="space-y-2">
                        @for ($i = 0; $i < 5; $i++)
                            <input name="poll_options[]" value="{{ old('poll_options.'.$i) }}" maxlength="120"
                                   placeholder="Opção {{ $i + 1 }}{{ $i < 2 ? '' : ' (opcional)' }}"
                                   class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @endfor
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-zinc-400">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="poll_multiple" value="1" @checked(old('poll_multiple'))>
                            Permitir múltipla escolha
                        </label>
                        <label class="inline-flex items-center gap-2">
                            Encerra em
                            <input type="datetime-local" name="poll_closes_at" value="{{ old('poll_closes_at') }}"
                                   class="rounded-lg border border-zinc-800 bg-zinc-900 px-2 py-1">
                        </label>
                    </div>
                </div>
                <div>
                    <label for="thread-attachments" class="mb-1 block text-xs font-semibold text-zinc-400">Anexos (imagens)</label>
                    <input id="thread-attachments" type="file" name="attachments[]" accept="image/*" multiple class="block w-full text-xs text-zinc-400">
                </div>
                <div>
                    <label for="thread-banner" class="mb-1 block text-xs font-semibold text-zinc-400">Banner do tópico (opcional)</label>
                    <input id="thread-banner" type="file" name="banner" accept="image/*" class="block w-full text-xs text-zinc-400">
                    <img id="thread-banner-preview" alt="Prévia do banner do tópico"
                         class="mt-2 hidden h-28 w-full max-w-md rounded-lg border border-zinc-800 object-cover">
                    <x-banner-crop preview-id="thread-banner-preview"
                                   :x="old('banner_position_x', 50)"
                                   :y="old('banner_position_y', 50)" />
                    {{-- Premium (coming soon): custom topic banners will be a paid feature. Public for now. --}}
                    <p class="mt-1 text-[11px] text-zinc-500">Imagem de fundo atrás do título, com degradê horizontal. Recurso público por enquanto.</p>
                </div>
                @if ($errors->any())
                    <p class="text-xs text-rose-400">{{ $errors->first() }}</p>
                @endif
                <button type="submit" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-indigo-500">Publicar</button>
            </form>
        </div>
    @elseif (auth()->check() && $category->is_locked)
        <p class="mb-4 rounded-lg border border-amber-800/50 bg-amber-500/10 px-4 py-2 text-sm text-amber-300">Este quadro está trancado para novos tópicos.</p>
    @endif

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
        @php($sorts = ['latest' => 'Atividade', 'newest' => 'Novos', 'replies' => 'Respostas', 'reactions' => 'Reações', 'unanswered' => 'Sem resposta'])
        @foreach ($sorts as $key => $label)
            <a href="{{ route('forum.category', array_filter(['categorySlug' => $category->slug, 'sort' => $key, 'filter' => $filter, 'tag' => $tag])) }}"
               class="rounded-full px-3 py-1 font-semibold transition {{ $sort === $key ? 'bg-zinc-100 text-zinc-950' : 'border border-zinc-700 text-zinc-300 hover:bg-zinc-900' }}">{{ $label }}</a>
        @endforeach
        @auth
            <a href="{{ route('forum.category', array_filter(['categorySlug' => $category->slug, 'sort' => $sort, 'filter' => 'mine', 'tag' => $tag])) }}"
               class="rounded-full px-3 py-1 font-semibold transition {{ $filter === 'mine' ? 'bg-zinc-100 text-zinc-950' : 'border border-zinc-700 text-zinc-300 hover:bg-zinc-900' }}">Meus</a>
        @endauth
        <a href="{{ route('forum.category', array_filter(['categorySlug' => $category->slug, 'sort' => $sort, 'filter' => 'solved', 'tag' => $tag])) }}"
           class="rounded-full px-3 py-1 font-semibold transition {{ $filter === 'solved' ? 'bg-zinc-100 text-zinc-950' : 'border border-zinc-700 text-zinc-300 hover:bg-zinc-900' }}">Resolvidos</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-800">
        <div class="divide-y divide-zinc-800">
            @forelse ($threads as $thread)
                <div class="flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-900/40">
                    <img src="{{ $thread->author?->avatar_image_path ? asset($thread->author->avatar_image_path) : Vite::asset('resources/images/default-avatar.svg') }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('forum.thread', ['categorySlug' => $category->slug, 'threadSlug' => $thread->slug]) }}"
                           class="font-semibold hover:underline">
                            @if ($thread->is_pinned)<span class="mr-1 text-indigo-400" title="Fixado">📌</span>@endif
                            @if ($thread->is_locked)<span class="mr-1" title="Trancado">🔒</span>@endif
                            @if ($thread->isSolved())<span class="mr-1" title="Resolvido">✅</span>@endif
                            {{ $thread->title }}
                        </a>
                        <p class="mt-0.5 text-xs text-zinc-400">
                            por
                            <a href="{{ route('profile.public.show.id', $thread->user_id) }}" data-hovercard="{{ $thread->author?->name }}" class="font-medium text-zinc-300 hover:underline">{{ $thread->author?->name ?? 'Usuário' }}</a>
                            · {{ $thread->created_at->diffForHumans() }}
                            @if ($thread->tags->isNotEmpty())
                                @foreach ($thread->tags as $tagItem)
                                    <a href="{{ route('forum.category', ['categorySlug' => $category->slug, 'tag' => $tagItem->slug]) }}" class="ml-1 rounded bg-zinc-800 px-1.5 py-0.5 text-[11px] hover:bg-zinc-700">#{{ $tagItem->name }}</a>
                                @endforeach
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right text-xs text-zinc-400">
                        <p class="font-bold text-zinc-200">{{ $thread->reply_count }}</p>
                        <p>respostas</p>
                        <p class="mt-1">{{ $thread->view_count }} views</p>
                    </div>
                    <div class="hidden shrink-0 text-right text-xs text-zinc-400 sm:block">
                        @if ($thread->lastPoster)
                            <p class="text-zinc-300">{{ $thread->lastPoster->name }}</p>
                        @endif
                        <p>{{ $thread->last_post_at?->diffForHumans() ?? $thread->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-500">Nenhum tópico aqui ainda.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4 flex justify-center">{{ $threads->links() }}</div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('thread-type');
        const poll = document.getElementById('poll-fields');
        if (!type || !poll) return;

        const sync = () => poll.classList.toggle('hidden', type.value !== 'poll');
        type.addEventListener('change', sync);
        sync();
    });

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('thread-banner');
        const preview = document.getElementById('thread-banner-preview');
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    });
</script>
@endpush
