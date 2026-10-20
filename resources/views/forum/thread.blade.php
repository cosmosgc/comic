@extends('layouts.forum-layout')

@section('title', $thread->title.' — Fórum')

@section('content')
<div class="mx-auto min-h-screen max-w-4xl px-4 py-6">

    <nav class="mb-3 text-xs text-zinc-500" aria-label="Trilha">
        <a href="{{ route('forum.index') }}" class="hover:text-zinc-300">Fórum</a>
        <span aria-hidden="true"> › </span>
        <a href="{{ route('forum.category', ['categorySlug' => $category->slug]) }}" class="hover:text-zinc-300">{{ $category->name }}</a>
    </nav>

    @php($bannerUrl = $thread->bannerUrl())
    <header @class(['mb-4', 'relative overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/60 px-5 py-6 sm:px-6' => $bannerUrl])>
        @if ($bannerUrl)
            {{-- Topic banner behind the header, fading out horizontally. --}}
            <img src="{{ $bannerUrl }}" alt="Banner do tópico {{ $thread->title }}" id="thread-banner-hero"
                 class="pointer-events-none absolute inset-0 h-full w-full object-cover"
                 style="object-position: {{ $thread->bannerPosition() }}; mask-image: linear-gradient(to right, #000 0%, #000 40%, transparent 100%); -webkit-mask-image: linear-gradient(to right, #000 0%, #000 40%, transparent 100%);">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-zinc-950/85 via-zinc-950/40 to-transparent" aria-hidden="true"></div>
        @endif
        <div class="relative">
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
        </div>
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

    @if ($canEditThread)
        {{-- Premium (coming soon): custom topic banners will be a paid feature. Public for now. --}}
        <details class="mb-4 rounded-xl border border-zinc-800 bg-zinc-900/40 px-4 py-3">
            <summary class="cursor-pointer text-xs font-semibold text-zinc-400">Banner do tópico</summary>
            <p class="mt-2 text-xs text-zinc-500">
                A imagem aparece atrás do título, com um degradê horizontal. Recurso público por enquanto —
                em breve os banners personalizados serão um recurso premium.
            </p>
            <form method="POST" action="{{ route('forum.threads.update', $thread) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                @method('PUT')
                <img id="thread-banner-preview" src="{{ $thread->bannerUrl() ?? '' }}" alt="Prévia do banner do tópico"
                     @class(['h-28 w-full max-w-md rounded-lg border border-zinc-800 object-cover', 'hidden' => ! $thread->banner_image_path])
                     style="object-position: {{ $thread->bannerPosition() }};">
                <div class="flex flex-wrap items-center gap-3">
                    <input id="thread-banner-input" type="file" name="banner" accept="image/*" class="text-xs text-zinc-400">
                    <button type="submit" class="rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-indigo-500">Salvar banner</button>
                    @if ($thread->banner_image_path)
                        <label class="inline-flex items-center gap-2 text-xs text-zinc-400">
                            <input type="checkbox" name="remove_banner" value="1">
                            Remover banner atual
                        </label>
                    @endif
                </div>
                <x-banner-crop preview-id="thread-banner-preview" hero-id="thread-banner-hero"
                               :x="old('banner_position_x', $thread->banner_position_x ?? 50)"
                               :y="old('banner_position_y', $thread->banner_position_y ?? 50)" />
                @error('banner')<span class="text-xs text-rose-400">{{ $message }}</span>@enderror
            </form>
        </details>
    @endif

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

    (function () {
        var input = document.getElementById('thread-banner-input');
        var preview = document.getElementById('thread-banner-preview');
        if (!input || !preview) return;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        });
    })();
</script>
@endsection
