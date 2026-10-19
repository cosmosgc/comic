@extends('layouts.forum-layout')

@section('title', $thread->title.' — Fórum')

@section('content')
<div class="mx-auto min-h-screen max-w-4xl px-4 py-6">

    <nav class="mb-3 text-xs text-zinc-500" aria-label="Trilha">
        <a href="{{ route('forum.index') }}" class="hover:text-zinc-300">Fórum</a>
        <span aria-hidden="true"> › </span>
        <a href="{{ route('forum.category', ['categorySlug' => $category->slug]) }}" class="hover:text-zinc-300">{{ $category->name }}</a>
    </nav>

    <header class="mb-4">
        <h1 class="text-2xl font-extrabold tracking-tight">
            @if ($thread->is_pinned)<span class="mr-1 text-indigo-400" title="Fixado">📌</span>@endif
            @if ($thread->is_locked)<span class="mr-1" title="Trancado">🔒</span>@endif
            @if ($thread->isSolved())<span class="mr-1" title="Resolvido">✅</span>@endif
            {{ $thread->title }}
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-400">
            <span class="rounded-full border border-zinc-700 px-2 py-0.5">{{ $thread->isPoll() ? 'Enquete' : ($thread->isQuestion() ? 'Pergunta' : 'Discussão') }}</span>
            <span>{{ $thread->view_count }} visualizações</span>
            <span>{{ $thread->reply_count }} respostas</span>
            @foreach ($thread->tags as $tag)
                <a href="{{ route('forum.category', ['categorySlug' => $category->slug, 'tag' => $tag->slug]) }}" class="rounded bg-zinc-800 px-1.5 py-0.5 hover:bg-zinc-700">#{{ $tag->name }}</a>
            @endforeach
        </p>
    </header>

    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        @auth
            <button type="button" data-url="{{ route('forum.threads.subscribe', $thread) }}" onclick="forumSubscribe(this)"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition {{ $subscribed ? 'border-indigo-500 bg-indigo-500/10 text-indigo-300' : 'border-zinc-700 text-zinc-300 hover:bg-zinc-900' }}">
                {{ $subscribed ? 'Inscrito' : 'Inscrever-se' }}
            </button>
        @endauth
        <button type="button" onclick="forumShare()" class="rounded-full border border-zinc-700 px-4 py-1.5 text-xs font-semibold text-zinc-300 transition hover:bg-zinc-900">Compartilhar</button>

        @if ($canModerate)
            <span class="ml-auto flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('forum.threads.pin', $thread) }}" class="inline">@csrf
                    <button type="submit" class="text-xs text-zinc-400 hover:text-zinc-100">{{ $thread->is_pinned ? 'Desafixar' : 'Fixar' }}</button>
                </form>
                <form method="POST" action="{{ route('forum.threads.lock', $thread) }}" class="inline">@csrf
                    <button type="submit" class="text-xs text-zinc-400 hover:text-zinc-100">{{ $thread->is_locked ? 'Reabrir' : 'Trancar' }}</button>
                </form>
                <form method="POST" action="{{ route('forum.threads.approve', $thread) }}" class="inline">@csrf
                    <button type="submit" class="text-xs text-zinc-400 hover:text-zinc-100">{{ $thread->is_approved ? 'Reprovar' : 'Aprovar' }}</button>
                </form>
                <form method="POST" action="{{ route('forum.threads.destroy', $thread) }}" class="inline" onsubmit="return confirm('Remover este tópico?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Remover</button>
                </form>
            </span>
        @endif
    </div>

    @include('forum.partials.poll', ['poll' => $poll ?? null, 'viewerPollVotes' => $viewerPollVotes ?? []])

    <div class="divide-y divide-zinc-800 overflow-hidden rounded-xl border border-zinc-800">
        @forelse ($posts as $post)
            @include('forum.partials.post', ['post' => $post])
        @empty
            <p class="p-6 text-center text-zinc-500">Este tópico não tem posts aprovados.</p>
        @endforelse
    </div>

    <div class="mt-3 flex justify-center">{{ $posts->links() }}</div>

    @auth
        @if (! $thread->is_locked || $canModerate)
            <div class="mt-6 rounded-xl border border-zinc-800 p-4">
                <h2 class="mb-2 text-sm font-bold">Responder</h2>
                <form method="POST" action="{{ route('forum.posts.store', $thread) }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <textarea name="body" rows="4" required class="w-full rounded-lg border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" placeholder="Sua resposta (Markdown)…">{{ old('body') }}</textarea>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="file" name="attachments[]" accept="image/*" multiple class="text-xs text-zinc-400">
                        <button type="submit" class="ml-auto rounded-full bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-500">Enviar resposta</button>
                    </div>
                    @if ($errors->any())<p class="text-xs text-rose-400">{{ $errors->first() }}</p>@endif
                </form>
            </div>
        @else
            <p class="mt-6 rounded-xl border border-amber-800/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">Este tópico está trancado.</p>
        @endif
    @else
        <p class="mt-6 rounded-xl border border-zinc-800 p-4 text-center text-sm text-zinc-400">
            <a href="{{ route('login') }}" class="font-semibold text-indigo-400 hover:underline">Entre</a> para responder.
        </p>
    @endauth

</div>

<script>
    window.forumReact = function (btn) {
        fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reaction: btn.dataset.reaction }),
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data) return;
            var count = btn.querySelector('.forum-react-count');
            if (count) count.textContent = data.count;
            btn.classList.toggle('border-indigo-500', data.active);
            btn.classList.toggle('bg-indigo-500/10', data.active);
            btn.classList.toggle('text-indigo-300', data.active);
            btn.classList.toggle('border-zinc-700', !data.active);
            btn.classList.toggle('text-zinc-300', !data.active);
        }).catch(function () {});
    };

    window.forumSubscribe = function (btn) {
        fetch(btn.dataset.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data) return;
            btn.textContent = data.subscribed ? 'Inscrito' : 'Inscrever-se';
            btn.classList.toggle('border-indigo-500', data.subscribed);
            btn.classList.toggle('bg-indigo-500/10', data.subscribed);
            btn.classList.toggle('text-indigo-300', data.subscribed);
            btn.classList.toggle('border-zinc-700', !data.subscribed);
            btn.classList.toggle('text-zinc-300', !data.subscribed);
        }).catch(function () {});
    };

    window.forumShare = function () {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ url: url, title: document.title });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(url);
            alert('Link copiado!');
        }
    };
</script>
@endsection
