@extends('layouts.admin-layout')

@section('title', 'Importar do E-Hentai')
@section('page-title', 'Importar do E-Hentai')

@section('content')
    @if (session('success'))
        <div class="admin-card mb-6 border-emerald-500/30 bg-emerald-500/10">
            <div class="admin-card-body text-sm text-emerald-700 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="admin-card mb-6 border-red-500/30 bg-red-500/10">
            <div class="admin-card-body text-sm text-red-700 dark:text-red-200">
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if ($stage === 'form')
        <div class="admin-card mb-6 max-w-2xl">
            <div class="admin-card-header">
                <span>1 · Buscar galeria</span>
            </div>
            <div class="admin-card-body">
                <p class="mb-4 text-sm opacity-80">
                    Cole a URL da galeria (<code>…/g/1234567/abcd…/</code>) ou de uma página dela.
                    O importador busca título, artista, tags e todas as imagens em tamanho original.
                </p>
                <form method="POST" action="{{ route('admin.import.preview') }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input type="text" name="url" required maxlength="500" value="{{ old('url') }}"
                           placeholder="https://e-hentai.org/g/…/…/"
                           class="admin-input min-w-0 flex-1">
                    <button type="submit" class="admin-btn admin-btn-success">Buscar</button>
                </form>
                @error('url')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        @if ($queue->isNotEmpty())
            <div class="admin-card max-w-2xl">
                <div class="admin-card-header">
                    <span>Fila de agendados</span>
                </div>
                <div class="admin-card-body text-sm">
                    <ul class="space-y-1">
                        @foreach ($queue as $item)
                            <li>
                                <a href="{{ route('comics.edit', $item->id) }}" class="text-indigo-600 hover:underline dark:text-indigo-300">
                                    {{ $item->title }}
                                </a>
                                <span class="opacity-60">· {{ $item->published_at->format('d/m/Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif

    @if ($stage === 'edit')
        <div class="admin-card mb-6 max-w-2xl">
            <div class="admin-card-header">
                <span>2 · Revisar e agendar</span>
            </div>
            <div class="admin-card-body">
                <div class="mb-4 flex items-start gap-3">
                    @if ($preview['thumb'])
                        <img src="{{ $preview['thumb'] }}" alt="" class="h-24 w-16 rounded object-cover">
                    @endif
                    <div class="min-w-0 text-sm">
                        <p class="font-medium">{{ $preview['title'] }}</p>
                        <p class="opacity-60">
                            {{ $preview['category'] ?? '—' }} · {{ count($preview['page_urls']) }} página(s)
                            @if ($preview['filecount'] > 0 && $preview['filecount'] !== count($preview['page_urls']))
                                (de {{ $preview['filecount'] }})
                            @endif
                        </p>
                        <a href="{{ $preview['source_url'] }}" target="_blank" rel="noopener"
                           class="text-indigo-600 hover:underline dark:text-indigo-300">abrir no E-Hentai ↗</a>
                        @if ($preview['truncated'])
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">
                                ⚠️ Nem todas as páginas foram listadas — só as encontradas serão importadas.
                            </p>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.import.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $previewToken }}">

                    <div>
                        <label for="import-title" class="admin-label">Título</label>
                        <input type="text" class="admin-input" name="title" id="import-title" required maxlength="255"
                               value="{{ old('title', $preview['title']) }}">
                        @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="import-author" class="admin-label">Autor / artista</label>
                        <input type="text" class="admin-input" name="author" id="import-author" maxlength="255"
                               value="{{ old('author', $preview['artist']) }}">
                        @error('author')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="import-desc" class="admin-label">Descrição</label>
                        <textarea class="admin-input" name="description" id="import-desc" rows="2" maxlength="5000">{{ old('description', $suggestedDescription) }}</textarea>
                        @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="admin-label">Tags ({{ count($preview['tags']) }} da galeria — edite à vontade)</label>
                        @include('components.tag-editor', [
                            'name' => 'tags',
                            'inputId' => 'import-tags',
                            'value' => old('tags', implode(', ', $preview['tags'])),
                            'placeholder' => 'Tags importadas — remova ou adicione…',
                        ])
                        @error('tags')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        @include('components.tag-lookup', ['tagsInputId' => 'import-tags', 'titleInputId' => 'import-title', 'authorInputId' => 'import-author'])
                    </div>

                    <div>
                        <label for="import-published" class="admin-label">Publicar em</label>
                        <input type="datetime-local" class="admin-input" name="published_at" id="import-published"
                               value="{{ old('published_at', $defaultDate) }}">
                        <p class="mt-1 text-xs opacity-60">
                            Padrão: um dia após o último agendado da fila (ou amanhã, se a fila estiver vazia).
                            Vazio = publica na hora.
                        </p>
                        @error('published_at')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="admin-btn admin-btn-success">Criar e baixar páginas</button>
                        <a href="{{ route('admin.import') }}" class="admin-btn">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($stage === 'progress')
        <div class="admin-card mb-6 max-w-2xl" id="import-progress"
             data-pages-url="{{ route('admin.import.pages', $comic) }}"
             data-total="{{ $total }}">
            <div class="admin-card-header">
                <span>3 · Baixando páginas</span>
            </div>
            <div class="admin-card-body">
                <p class="mb-1 text-sm font-medium">{{ $comic->title }}</p>
                <p class="mb-3 text-xs opacity-60">
                    Agendado para {{ $comic->published_at?->format('d/m/Y H:i') ?? 'publicação imediata' }} ·
                    <a href="{{ route('comics.edit', $comic->id) }}" class="text-indigo-600 hover:underline dark:text-indigo-300">editar</a>
                </p>
                <div class="h-4 overflow-hidden rounded-lg bg-zinc-200 dark:bg-zinc-800">
                    <div id="import-bar" class="h-full w-0 bg-indigo-600 text-center text-xs font-medium text-white transition-all" style="width:0%">0%</div>
                </div>
                <p id="import-status" class="mt-2 text-sm opacity-80">Iniciando…</p>
                <p id="import-failed" class="mt-1 hidden text-xs text-red-500"></p>
                <div id="import-done" class="mt-3 hidden flex-wrap gap-2">
                    <a href="{{ route('comics.showById', $comic->id) }}" class="admin-btn admin-btn-success admin-btn-sm">Ver comic</a>
                    <a href="{{ route('comics.edit', $comic->id) }}" class="admin-btn admin-btn-sm">Editar</a>
                    <a href="{{ route('admin.import') }}" class="admin-btn admin-btn-sm">Importar outro</a>
                </div>
                <form method="POST" action="{{ route('admin.import.destroy', $comic) }}" class="mt-4"
                      onsubmit="return confirm('Cancelar a importação e apagar este comic?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" id="import-cancel" class="admin-btn admin-btn-sm">Cancelar e apagar</button>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
@if ($stage === 'progress')
<script>
(function () {
    var root = document.getElementById('import-progress');
    if (!root) return;
    var bar = document.getElementById('import-bar');
    var status = document.getElementById('import-status');
    var failed = document.getElementById('import-failed');
    var done = document.getElementById('import-done');
    var cancel = document.getElementById('import-cancel');
    var total = parseInt(root.dataset.total, 10) || 0;

    function paint(count) {
        var pct = total === 0 ? 100 : Math.round((count / total) * 100);
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        status.textContent = 'Baixadas ' + count + ' de ' + total + ' página(s)…';
    }

    function finish(data) {
        paint(data.done);
        if ((data.failed || []).length) {
            failed.textContent = '⚠️ ' + data.failed.length + ' página(s) falharam após 3 tentativas e foram puladas.';
            failed.classList.remove('hidden');
        }
        status.textContent = 'Concluído: ' + data.done + ' página(s) importada(s).';
        done.classList.remove('hidden');
        done.classList.add('flex');
        if (cancel) cancel.disabled = true;
    }

    function poll() {
        fetch(root.dataset.pagesUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, status: r.status, body: b }; }); })
        .then(function (res) {
            if (!res.ok) {
                // 409 = outro poll ainda rodando; tenta de novo em seguida.
                setTimeout(poll, res.status === 409 ? 3000 : 5000);
                if (res.status !== 409) status.textContent = 'Erro (' + res.status + ') — tentando de novo…';
                return;
            }
            if (res.body.complete) { finish(res.body); return; }
            paint(res.body.done);
            setTimeout(poll, 800);
        })
        .catch(function () {
            status.textContent = 'Erro de rede — tentando de novo…';
            setTimeout(poll, 5000);
        });
    }

    paint({{ $done }});
    poll();
})();
</script>
@endif
@endsection
