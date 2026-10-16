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
            <div class="admin-card mb-6 max-w-2xl">
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

        <details class="admin-card max-w-2xl">
            <summary class="admin-card-header cursor-pointer">
                <span>Configurações de importação</span>
            </summary>
            <div class="admin-card-body">
                <p class="mb-4 text-sm opacity-80">
                    Salvas no banco (valem após deploy e restart). Campo vazio = volta ao padrão do código.
                </p>
                <form method="POST" action="{{ route('admin.import.settings') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @csrf
                    @foreach ($settingDefs as $key => $def)
                        <div class="{{ ($def['type'] ?? 'string') === 'string' ? 'sm:col-span-2' : '' }}">
                            <label for="setting-{{ $key }}" class="admin-label">{{ $def['label'] }}</label>
                            @if (($def['type'] ?? 'string') === 'int')
                                <input type="number" class="admin-input" name="{{ $key }}" id="setting-{{ $key }}"
                                       min="{{ $def['min'] }}" max="{{ $def['max'] }}" step="1"
                                       value="{{ old($key, config("ehimport.{$key}")) }}">
                            @else
                                <input type="text" class="admin-input" name="{{ $key }}" id="setting-{{ $key }}"
                                       maxlength="{{ $def['maxlen'] ?? 500 }}" placeholder="(vazio = direto, sem proxy)"
                                       value="{{ old($key, config("ehimport.{$key}")) }}">
                            @endif
                            <p class="mt-1 text-xs opacity-60">{{ $def['hint'] }}</p>
                            @error($key)<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <button type="submit" class="admin-btn admin-btn-success">Salvar configurações</button>
                    </div>
                </form>
            </div>
        </details>
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
        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
        <div class="admin-card mb-6" id="import-progress"
             data-pages-url="{{ route('admin.import.pages', $comic) }}"
             data-retry-url="{{ route('admin.import.retry', $comic) }}"
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
                <p id="import-stats" class="mt-1 text-xs opacity-60"></p>
                <p id="import-failed" class="mt-1 hidden text-xs text-red-500"></p>
                @if ($total > 0)
                    <div id="import-pages" class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5"
                         data-retry-page-url="{{ route('admin.import.retry-page', $comic) }}">
                        @for ($n = 1; $n <= $total; $n++)
                            @php($thumb = $pageMap[$n] ?? null)
                            @php($isFailed = in_array($n, $failed ?? []))
                            <div data-page-row="{{ $n }}" class="relative cursor-pointer overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-900"
                                 @if ($isFailed && !empty($errors[$n] ?? null)) title="{{ $errors[$n] }} (clique para inspecionar)" @else title="Clique para inspecionar" @endif>
                                <span class="absolute left-1 top-1 z-10 rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $n }}</span>
                                <img src="{{ $thumb ?? '' }}" alt="Página {{ $n }}" loading="lazy" data-page-img
                                     class="{{ $thumb ? '' : 'hidden ' }}aspect-[3/4] w-full object-cover">
                                <div data-page-empty class="{{ $thumb ? 'hidden' : '' }} flex aspect-[3/4] w-full items-center justify-center px-1 text-center text-xs opacity-60">
                                    <span data-page-state>{{ $isFailed ? '✕ falhou' : '… na fila' }}</span>
                                </div>
                                <button type="button" data-page-retry="{{ $n }}"
                                        class="{{ $isFailed ? '' : 'hidden ' }}absolute bottom-1 right-1 rounded bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white hover:bg-red-500">Tentar de novo</button>
                            </div>
                        @endfor
                    </div>
                @else
                    <p class="mt-3 text-sm opacity-60">Nenhuma página na fila.</p>
                @endif
                <div id="import-done" class="mt-3 hidden flex-wrap gap-2">
                    <a href="{{ route('comics.showById', $comic->id) }}" class="admin-btn admin-btn-success admin-btn-sm">Ver comic</a>
                    <a href="{{ route('comics.edit', $comic->id) }}" class="admin-btn admin-btn-sm">Editar</a>
                    <a href="{{ route('admin.import') }}" class="admin-btn admin-btn-sm">Importar outro</a>
                </div>
                <div id="import-retry-row" class="mt-3 hidden">
                    <button type="button" id="import-retry" class="admin-btn admin-btn-success admin-btn-sm">Tentar falhadas de novo</button>
                </div>

                <!-- Inspect modal: per-page details (error, sizes, links, actions) -->
                <div id="import-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
                    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 id="import-modal-title" class="text-base font-bold">Página</h3>
                            <button type="button" id="import-modal-close" class="rounded-lg px-2 py-1 text-xl leading-none opacity-60 hover:opacity-100" aria-label="Fechar">×</button>
                        </div>
                        <div id="import-modal-thumb" class="mb-3 hidden">
                            <img id="import-modal-img" src="" alt="" class="max-h-64 w-full rounded-lg object-contain bg-zinc-100 dark:bg-zinc-950">
                        </div>
                        <dl id="import-modal-rows" class="space-y-1.5 text-sm"></dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a id="import-modal-download" href="#" data-file-url-template="{{ route('admin.import.page-file', [$comic, '__N__']) }}" class="admin-btn admin-btn-sm">Baixar imagem</a>
                            <button type="button" id="import-modal-retry" class="admin-btn admin-btn-success admin-btn-sm">Tentar de novo</button>
                        </div>
                    </div>
                </div>

                <details id="import-logbox" class="mt-4 text-sm xl:hidden">
                    <summary class="cursor-pointer opacity-70 hover:opacity-100">Log ({{ count($logLines ?? []) }})</summary>
                    <div class="mt-2 max-h-48 space-y-0.5 overflow-y-auto rounded-lg bg-zinc-100 p-3 font-mono text-xs dark:bg-zinc-950" data-import-log>
                        @forelse ($logLines ?? [] as $line)
                            <p>{{ $line }}</p>
                        @empty
                            <p class="opacity-60">Sem eventos ainda.</p>
                        @endforelse
                    </div>
                </details>
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer opacity-70 hover:opacity-100">Baixar todas as páginas novamente</summary>
                    <p class="mt-1 text-xs opacity-60">
                        Apaga as páginas atuais e baixa a galeria inteira de novo, em ordem.
                        Use quando faltam páginas de um import antigo (a fila de tentativas expira após algumas horas).
                    </p>
                    <form method="POST" action="{{ route('admin.import.redownload', $comic) }}" class="mt-2 flex flex-wrap gap-2"
                          onsubmit="return confirm('Apagar as {{ $comic->pages()->count() }} página(s) atuais e baixar tudo de novo?')">
                        @csrf
                        <input type="text" name="url" required maxlength="500" placeholder="https://e-hentai.org/g/…/…/"
                               class="admin-input min-w-0 flex-1">
                        <button type="submit" class="admin-btn admin-btn-sm">Baixar de novo</button>
                    </form>
                </details>
                <form method="POST" action="{{ route('admin.import.destroy', $comic) }}" class="mt-4"
                      onsubmit="return confirm('Cancelar a importação e apagar este comic?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" id="import-cancel" class="admin-btn admin-btn-sm">Cancelar e apagar</button>
                </form>
            </div>
        </div>
            </div>
            <div class="hidden xl:block">
                <div class="admin-card lg:sticky lg:top-20">
                    <div class="admin-card-header">
                        <span>Log</span>
                    </div>
                    <div class="admin-card-body">
                        <div class="max-h-[70vh] space-y-0.5 overflow-y-auto rounded-lg bg-zinc-100 p-3 font-mono text-xs dark:bg-zinc-950" data-import-log>
                            @forelse ($logLines ?? [] as $line)
                                <p>{{ $line }}</p>
                            @empty
                                <p class="opacity-60">Sem eventos ainda.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
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
    var retryRow = document.getElementById('import-retry-row');
    var retryBtn = document.getElementById('import-retry');
    var pagesBox = document.getElementById('import-pages');
    var statsLine = document.getElementById('import-stats');
    var logBoxes = document.querySelectorAll('[data-import-log]');
    var modal = document.getElementById('import-modal');
    var modalTitle = document.getElementById('import-modal-title');
    var modalThumb = document.getElementById('import-modal-thumb');
    var modalImg = document.getElementById('import-modal-img');
    var modalRows = document.getElementById('import-modal-rows');
    var modalDownload = document.getElementById('import-modal-download');
    var modalRetry = document.getElementById('import-modal-retry');
    var total = parseInt(root.dataset.total, 10) || 0;
    var polling = false;
    var rows = {};
    var lastDetails = {};
    var lastPages = {};
    if (pagesBox) {
        pagesBox.querySelectorAll('[data-page-row]').forEach(function (row) {
            rows[row.getAttribute('data-page-row')] = row;
        });
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtBytes(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    }

    function paintStats(data) {
        var fails = (data.failed || []).length;
        statsLine.textContent = data.done + ' de ' + data.total + ' páginas · ' + fails + ' falhada(s) · ' + fmtBytes(data.total_size || 0) + ' baixados';
    }

    function paintLog(lines) {
        var html = (!lines || !lines.length)
            ? '<p class="opacity-60">Sem eventos ainda.</p>'
            : lines.map(function (l) { return '<p>' + esc(l) + '</p>'; }).join('');
        logBoxes.forEach(function (box) {
            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;
        });
    }

    function setRow(number, mode, thumbUrl) {
        var row = rows[String(number)];
        if (!row) return;
        var img = row.querySelector('[data-page-img]');
        var empty = row.querySelector('[data-page-empty]');
        var state = row.querySelector('[data-page-state]');
        var btn = row.querySelector('[data-page-retry]');
        if (mode === 'done' && thumbUrl) {
            if (img.getAttribute('src') !== thumbUrl) img.setAttribute('src', thumbUrl);
            img.classList.remove('hidden');
            if (empty) empty.classList.add('hidden');
            if (btn) btn.classList.add('hidden');
        } else if (mode === 'failed') {
            if (state) state.textContent = '✕ falhou';
            if (btn) btn.classList.remove('hidden');
        } else {
            if (state) state.textContent = '… na fila';
            if (btn) btn.classList.add('hidden');
        }
    }

    function paintPages(data) {
        var thumbs = data.pages || {};
        var fails = data.failed || [];
        var errs = data.errors || {};
        lastPages = thumbs;
        lastDetails = data.details || lastDetails;
        for (var n = 1; n <= total; n++) {
            if (thumbs[n]) {
                setRow(n, 'done', thumbs[n]);
            } else if (fails.indexOf(n) !== -1) {
                setRow(n, 'failed');
                var row = rows[String(n)];
                if (row && errs[n]) row.setAttribute('title', errs[n] + ' (clique para inspecionar)');
            } else {
                setRow(n, 'pending');
            }
        }
    }

    var statusLabels = { done: 'Baixada ✓', failed: 'Falhou ✕', pending: 'Na fila …', downloading: 'Baixando…' };

    function openModal(n) {
        var d = lastDetails[n] || {};
        var thumb = lastPages[n] || null;
        modalTitle.textContent = 'Página ' + n + ' de ' + total;
        if (thumb) {
            modalImg.setAttribute('src', thumb);
            modalThumb.classList.remove('hidden');
            modalDownload.classList.remove('hidden');
        } else {
            modalThumb.classList.add('hidden');
            modalDownload.classList.add('hidden');
        }
        function row(label, value, raw) {
            return '<div class="flex gap-2"><dt class="w-24 shrink-0 opacity-60">' + esc(label) + '</dt><dd class="min-w-0 flex-1 break-words">' + (raw ? value : esc(value)) + '</dd></div>';
        }
        var html = '';
        html += row('Estado', statusLabels[d.status] || d.status || '—');
        html += row('Tamanho', d.size ? fmtBytes(d.size) : '—');
        html += row('Tentativas', d.attempts !== undefined ? d.attempts : '—');
        html += row('Atualizada', d.updated || '—');
        html += row('Página /s/', d.page_url ? '<a class="text-indigo-600 hover:underline dark:text-indigo-300" target="_blank" rel="noopener" href="' + esc(d.page_url) + '">abrir ↗</a>' : '—', true);
        html += row('Original', d.image_url ? '<a class="text-indigo-600 hover:underline dark:text-indigo-300" target="_blank" rel="noopener" href="' + esc(d.image_url) + '">abrir ↗</a>' : '—', true);
        html += row('Erro', d.error || '—');
        modalRows.innerHTML = html;
        modalDownload.setAttribute('href', modalDownload.getAttribute('data-file-url-template').replace('__N__', n));
        modalRetry.setAttribute('data-number', n);
        modalRetry.classList.toggle('hidden', (d.status || '') !== 'failed');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function paint(count) {
        var pct = total === 0 ? 100 : Math.round((count / total) * 100);
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        status.textContent = 'Baixadas ' + count + ' de ' + total + ' página(s)…';
    }

    function finish(data) {
        polling = false;
        paint(data.done);
        paintStats(data);
        paintPages(data);
        paintLog(data.log || []);
        var fails = data.failed || [];
        var errs = data.errors || {};
        if (fails.length) {
            var firstErr = errs[fails[0]] || '';
            failed.textContent = '⚠️ ' + fails.length + ' página(s) falharam após 3 tentativas e foram puladas (nº ' + fails.join(', ') + ').'
                + (firstErr ? ' Motivo: ' + firstErr : '');
            failed.classList.remove('hidden');
            if (retryRow) retryRow.classList.remove('hidden');
        } else {
            failed.classList.add('hidden');
            if (retryRow) retryRow.classList.add('hidden');
        }
        status.textContent = 'Concluído: ' + data.done + ' página(s) importada(s).';
        done.classList.remove('hidden');
        done.classList.add('flex');
        if (cancel) cancel.disabled = true;
    }

    function poll() {
        if (!polling) return;
        fetch(root.dataset.pagesUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
        .then(function (r) {
            var retryAfter = parseInt(r.headers.get('Retry-After') || '0', 10) || 0;
            return r.json().then(function (b) {
                return { ok: r.ok, status: r.status, body: b, retryAfter: retryAfter };
            }, function () {
                return { ok: r.ok, status: r.status, body: {}, retryAfter: retryAfter };
            });
        })
        .then(function (res) {
            if (!res.ok) {
                // 409 = outro poll ainda rodando; 429 = teto de downloads
                // (espera o Retry-After); resto tenta em 5s.
                var wait = res.status === 409 ? 3 : (res.retryAfter > 0 ? res.retryAfter : 5);
                if (res.status === 429) {
                    status.textContent = 'Pausa anti-bloqueio: retomando em ' + wait + 's…';
                } else if (res.status !== 409) {
                    status.textContent = 'Erro (' + res.status + ') — tentando de novo…';
                }
                setTimeout(poll, wait * 1000);
                return;
            }
            if (res.body.complete) { finish(res.body); return; }
            paint(res.body.done);
            paintStats(res.body);
            paintPages(res.body);
            paintLog(res.body.log || []);
            setTimeout(poll, 800);
        })
        .catch(function () {
            status.textContent = 'Erro de rede — tentando de novo…';
            setTimeout(poll, 5000);
        });
    }

    function startPolling() {
        if (polling) return;
        polling = true;
        if (retryRow) retryRow.classList.add('hidden');
        done.classList.add('hidden');
        done.classList.remove('flex');
        poll();
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', function () {
            retryBtn.disabled = true;
            status.textContent = 'Recolocando as falhadas na fila…';
            fetch(root.dataset.retryUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json(); })
            .then(function () {
                retryBtn.disabled = false;
                status.textContent = 'Reiniciando o download…';
                startPolling();
            })
            .catch(function () {
                retryBtn.disabled = false;
                status.textContent = 'Erro ao tentar de novo — recarregue a página.';
            });
        });
    }

    paint({{ $done }});
    @if (!empty($failed))
        finish({ done: {{ $done }}, total: total, failed: @json($failed), errors: @json($errors ?? []), pages: @json($pageMap), details: @json($details ?? []), total_size: {{ (int) ($totalSize ?? 0) }}, log: @json($logLines ?? []) });
    @else
        startPolling();
    @endif
    if (pagesBox) {
        pagesBox.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-page-retry]');
            if (btn) {
                retrySingle(
                    parseInt(btn.getAttribute('data-page-retry'), 10),
                    btn,
                    function () {
                        btn.disabled = false;
                        btn.textContent = 'Tentar de novo';
                    }
                );
                return;
            }
            var rowEl = e.target.closest('[data-page-row]');
            if (rowEl) openModal(parseInt(rowEl.getAttribute('data-page-row'), 10));
        });
    }

    function retrySingle(number, btn, reset) {
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Tentando…';
        }
        fetch(pagesBox.dataset.retryPageUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ number: number }),
        })
        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
        .then(function (res) {
            if (!res.ok) {
                if (reset) reset();
                status.textContent = res.body.message || 'Não foi possível tentar de novo.';
                return;
            }
            setRow(number, 'pending');
            status.textContent = 'Página ' + number + ' de volta na fila…';
            closeModal();
            startPolling();
        })
        .catch(function () {
            if (reset) reset();
        });
    }

    document.getElementById('import-modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
    modalRetry.addEventListener('click', function () {
        retrySingle(parseInt(modalRetry.getAttribute('data-number'), 10), modalRetry, function () {
            modalRetry.textContent = 'Tentar de novo';
        });
    });
})();
</script>
@endif
@endsection
