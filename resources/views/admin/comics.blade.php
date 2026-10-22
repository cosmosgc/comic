@extends('layouts.admin-layout')

@section('title', 'Comics')

@section('page-title', 'Comics')

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

    {{-- Toast for in-place deletes: no reload, so no flash message. --}}
    <div data-comics-flash hidden
         class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 shadow-lg dark:text-emerald-200"
         role="status" aria-live="polite">
        <span data-comics-flash-text></span>
    </div>

    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Transferir entre instalações</span>
        </div>
        <div class="admin-card-body text-sm">
            <p class="mb-3 opacity-80">
                Exporte um comic abaixo e importe o <code>.zip</code> na outra instalação
                (ex.: local → remoto). Metadados, tags, ordem e capa viajam juntos; IDs novos, sem conflito.
            </p>
            <form method="POST" action="{{ route('admin.comics.package-import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="package" required accept=".zip,application/zip" class="text-sm">
                <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">Importar pacote</button>
            </form>
            @error('package')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs opacity-60">Limite do servidor: {{ ini_get('upload_max_filesize') }} por arquivo.</p>
        </div>
    </div>

    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Filtrar comics que precisam de atenção</span>
        </div>
        <div class="admin-card-body">
            <nav class="flex flex-wrap gap-2" aria-label="Filtros de comics">
                <button type="button" data-comics-filter-all
                        class="admin-btn admin-btn-sm {{ $activeFilters === [] ? 'admin-btn-success' : '' }}"
                        aria-pressed="{{ $activeFilters === [] ? 'true' : 'false' }}">
                    Todos
                </button>
                <button type="button" data-comics-filter="scheduled"
                        class="admin-btn admin-btn-sm {{ in_array('scheduled', $activeFilters, true) ? 'admin-btn-success' : '' }}"
                        aria-pressed="{{ in_array('scheduled', $activeFilters, true) ? 'true' : 'false' }}">
                    Em breve
                </button>
                <button type="button" data-comics-filter="untagged"
                        class="admin-btn admin-btn-sm {{ in_array('untagged', $activeFilters, true) ? 'admin-btn-success' : '' }}"
                        aria-pressed="{{ in_array('untagged', $activeFilters, true) ? 'true' : 'false' }}">
                    Sem tags
                </button>
                <button type="button" data-comics-filter="duplicates"
                        class="admin-btn admin-btn-sm {{ in_array('duplicates', $activeFilters, true) ? 'admin-btn-success' : '' }}"
                        aria-pressed="{{ in_array('duplicates', $activeFilters, true) ? 'true' : 'false' }}">
                    Possíveis duplicatas
                </button>
                @if ($transferConfigured)
                    <button type="button" data-comics-filter="remote"
                            class="admin-btn admin-btn-sm {{ in_array('remote', $activeFilters, true) ? 'admin-btn-success' : '' }}"
                            aria-pressed="{{ in_array('remote', $activeFilters, true) ? 'true' : 'false' }}">
                        Fora do remoto
                    </button>
                @endif
            </nav>
            <p class="mt-3 text-xs opacity-70">
                Os filtros podem ser combinados — com mais de um ativo, só aparecem os comics que atendem a todos.
                “Em breve” lista os comics com lançamento agendado para uma data futura.
                Denúncias de comics não estão disponíveis: as denúncias atuais se referem a posts.
                @if ($transferConfigured)
                    “Fora do remoto” lista os comics sem envio concluído para a outra instalação.
                @endif
            </p>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span>
                <span data-comics-heading>{{ $activeFilters === [] ? 'Todos os comics' : 'Comics filtrados' }}</span>
                <span class="ml-1 text-xs font-normal opacity-60" data-comics-count>({{ $comics->count() }})</span>
            </span>
        </div>
        @if ($transferConfigured)
            <div class="admin-card-body flex flex-wrap items-center gap-3 border-b border-zinc-200 text-sm dark:border-zinc-800">
                <label class="flex items-center gap-2">
                    <input type="checkbox" class="rounded" data-comics-select-all>
                    <span>Selecionar todos os listados</span>
                </label>
                <span class="text-xs opacity-60" data-comics-selected>0 selecionado(s)</span>
                <button type="button" class="admin-btn admin-btn-success admin-btn-sm" data-comics-batch disabled>
                    Enviar selecionados p/ remoto
                </button>
                <span class="text-xs opacity-70" data-comics-batch-status></span>
            </div>
        @endif
        <div class="admin-table-wrap">
            <table id="comicsTable" class="admin-table admin-table-compact table-fixed">
                <thead>
                    <tr>
                        @if ($transferConfigured)
                            <th class="w-[3%]"><span class="sr-only">Selecionar</span></th>
                        @endif
                        <th class="w-[4%]">ID</th>
                        <th class="w-[14%]">Nome</th>
                        <th class="w-[8%]">Autor</th>
                        <th class="w-[9%]">Slug</th>
                        <th class="w-[7%]">Criado em</th>
                        <th class="w-[10%]">Publicação</th>
                        <th class="w-[11%]">Agendar</th>
                        <th class="w-[5%]">Views</th>
                        <th class="w-[5%]">Páginas</th>
                        <th class="w-[7%]">Tamanho (MB)</th>
                        <th class="w-[13%]">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comics as $comic)
                        <tr data-untagged="{{ isset($untaggedIds[$comic->id]) ? '1' : '0' }}"
                            data-duplicate="{{ isset($duplicateIds[$comic->id]) ? '1' : '0' }}"
                            data-scheduled="{{ $comic->isScheduled() ? '1' : '0' }}"
                            @if ($transferConfigured) data-remote="{{ $comic->transfer?->isDone() ? '0' : '1' }}" @endif>
                            @if ($transferConfigured)
                                <td>
                                    @if (! $comic->transfer?->isDone())
                                        <input type="checkbox" class="mt-1 rounded" data-comic-check
                                               value="{{ $comic->id }}"
                                               aria-label="Selecionar {{ $comic->title }}">
                                    @endif
                                </td>
                            @endif
                            <td>{{ $comic->id }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if ($comic->image_path)
                                        <a href="{{ route('comics.showBySlug', $comic->slug) }}"
                                           class="shrink-0"
                                           title="Abrir {{ $comic->title }}">
                                            <img src="{{ asset('storage/'.$comic->image_path) }}"
                                                 alt="Capa de {{ $comic->title }}"
                                                 loading="lazy"
                                                 class="h-14 w-10 rounded border border-zinc-300/60 object-cover dark:border-zinc-700">
                                        </a>
                                    @else
                                        <span data-cover-placeholder
                                              class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-zinc-400/60 text-[10px] opacity-60 dark:border-zinc-600"
                                              title="Sem capa">—</span>
                                    @endif
                                    <a href="{{ route('comics.showBySlug', $comic->slug) }}"
                                       class="block min-w-0 truncate font-medium text-indigo-600 hover:underline dark:text-indigo-300"
                                       title="{{ $comic->title }}">{{ $comic->title }}</a>
                                </div>
                            </td>
                            <td>{{ $comic->author }}</td>
                            <td><code class="block max-w-full truncate" title="{{ $comic->slug }}">{{ $comic->slug }}</code></td>
                            <td class="whitespace-nowrap" data-order="{{ $comic->created_at?->timestamp }}" title="{{ $comic->created_at }}">
                                {{ $comic->created_at?->format('d/m/Y') }}
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($comic->isScheduled())
                                    <span class="admin-badge admin-badge-warning" title="Ainda não lançado no site">⏳ Em breve</span>
                                    <div class="mt-1 text-xs opacity-70">lança {{ $comic->published_at->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="admin-badge admin-badge-success">Publicado</span>
                                @endif
                                @if ($comic->isExclusive())
                                    <div class="mt-1">
                                        <span class="admin-badge admin-badge-info" title="Só membros logados leem; visitantes veem o card bloqueado">🔒 Exclusivo</span>
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('admin.comics.exclusive', $comic) }}" class="mt-1" data-comics-filter-form>
                                    @csrf
                                    @foreach ($activeFilters as $filter)
                                        <input type="hidden" name="filters[]" value="{{ $filter }}" data-comics-filter-input>
                                    @endforeach
                                    <input type="hidden" name="is_exclusive" value="{{ $comic->isExclusive() ? 0 : 1 }}">
                                    <button type="submit"
                                            class="admin-btn admin-btn-sm {{ $comic->isExclusive() ? 'admin-btn-warning' : '' }}"
                                            title="{{ $comic->isExclusive() ? 'Voltar a público: qualquer visitante lê' : 'Só membros logados leem; visitantes veem o card bloqueado' }}">
                                        {{ $comic->isExclusive() ? 'Tornar público' : 'Tornar exclusivo' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.comics.schedule', $comic) }}" class="flex flex-wrap items-center gap-1" data-comics-filter-form>
                                    @csrf
                                    @foreach ($activeFilters as $filter)
                                        <input type="hidden" name="filters[]" value="{{ $filter }}" data-comics-filter-input>
                                    @endforeach
                                    <input type="datetime-local" name="published_at"
                                           value="{{ $comic->published_at?->format('Y-m-d\TH:i') }}"
                                           class="admin-input admin-input-sm min-w-0 max-w-full"
                                           aria-label="Data de publicação de {{ $comic->title }}">
                                    <button type="submit" class="admin-btn admin-btn-sm w-full">Salvar</button>
                                </form>
                                @if ($comic->isScheduled())
                                    <form method="POST" action="{{ route('admin.comics.schedule', $comic) }}" class="mt-1" data-comics-filter-form>
                                        @csrf
                                        @foreach ($activeFilters as $filter)
                                            <input type="hidden" name="filters[]" value="{{ $filter }}" data-comics-filter-input>
                                        @endforeach
                                        <input type="hidden" name="published_at" value="">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-success">Publicar agora</button>
                                    </form>
                                @endif
                            </td>
                            <td>{{ $comic->view_count }}</td>
                            <td class="whitespace-nowrap" data-order="{{ $pageCounts[$comic->id] ?? 0 }}">
                                {{ $pageCounts[$comic->id] ?? 0 }}
                            </td>
                            @php($comicBytes = (int) ($pageBytes[$comic->id] ?? 0))
                            <td class="whitespace-nowrap" data-order="{{ $comicBytes }}" title="{{ number_format($comicBytes, 0, ',', '.') }} bytes">
                                {{ number_format($comicBytes / 1048576, 1, ',', ' ') }}
                            </td>
                            <td>
                                <div class="grid grid-cols-2 items-start gap-1">
                                    <a href="{{ route('comics.edit', $comic->id) }}" class="admin-btn admin-btn-sm w-full">Editar</a>
                                    <a href="{{ route('admin.comics.package', $comic) }}" class="admin-btn admin-btn-sm w-full">Exportar</a>
                                    @if ($transferConfigured ?? false)
                                        @if ($comic->transfer?->isDone() && $comic->transfer->remote_view_url)
                                            <a href="{{ $comic->transfer->remote_view_url }}" target="_blank" rel="noopener" class="admin-btn admin-btn-sm w-full whitespace-normal" title="Comic {{ $comic->transfer->remote_comic_id }} no remoto">✓ No remoto ↗</a>
                                        @else
                                            <button type="button" data-transfer="{{ $comic->id }}"
                                                    data-transfer-url="{{ route('admin.comics.transfer', $comic) }}"
                                                    class="admin-btn admin-btn-success admin-btn-sm w-full whitespace-normal">
                                                {{ $comic->transfer ? 'Continuar envio ('.min($comic->transfer->next_page - 1, $comic->transfer->total_pages).'/'.$comic->transfer->total_pages.')' : 'Enviar p/ remoto' }}
                                            </button>
                                        @endif
                                    @endif
                                    <form action="{{ route('admin.comics.destroy', $comic) }}" method="POST" class="w-full"
                                          data-comic-delete data-comics-filter-form
                                          onsubmit="return confirm('Excluir &quot;{{ $comic->title }}&quot; com todas as páginas e arquivos? Isso não pode ser desfeito.')">
                                        @csrf
                                        @method('DELETE')
                                        @foreach ($activeFilters as $filter)
                                            <input type="hidden" name="filters[]" value="{{ $filter }}" data-comics-filter-input>
                                        @endforeach
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger w-full">Excluir</button>
                                    </form>
                                    <span data-transfer-status="{{ $comic->id }}" class="col-span-2 text-xs opacity-70"></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        // Deferred module: jQuery/DataTables come from resources/js/admin.js.
        var transferConfigured = @json($transferConfigured);
        var transferStartUrl = '{{ route('admin.comics.transfer', ['comic' => '__ID__']) }}';
        var csrf = '{{ csrf_token() }}';

        // Attention filters are combined with AND and applied client-side:
        // every row is rendered and DataTables already paginates locally.
        var activeFilters = new Set(@json($activeFilters));
        var filterLabels = { scheduled: 'Em breve', untagged: 'Sem tags', duplicates: 'Possíveis duplicatas', remote: 'Fora do remoto' };
        var filterAttrs = { scheduled: 'data-scheduled', untagged: 'data-untagged', duplicates: 'data-duplicate', remote: 'data-remote' };

        // Registered before DataTables initializes, on top of the search box.
        if ($.fn.dataTable) {
            $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex) {
                if (!settings.table || settings.table.id !== 'comicsTable' || activeFilters.size === 0) {
                    return true;
                }
                var row = settings.data[dataIndex] && settings.data[dataIndex].tr;
                if (!row) {
                    return true;
                }
                return Array.from(activeFilters).every(function (filter) {
                    var attr = filterAttrs[filter];
                    return !attr || row.getAttribute(attr) === '1';
                });
            });
        }

        function comicsTable() {
            if (!window.jQuery || !$.fn.dataTable || !$.fn.dataTable.isDataTable('#comicsTable')) {
                return null;
            }
            return $('#comicsTable').DataTable();
        }

        // Keep the URL shareable/bookmarkable: ?filters[]=untagged&filters[]=remote.
        function syncFiltersUrl() {
            if (!window.history || !history.replaceState) {
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.delete('attention');
            // Remove both ?filters[]= and the indexed ?filters[0]= forms.
            Array.from(url.searchParams.keys()).forEach(function (key) {
                if (key === 'filters' || key.indexOf('filters[') === 0) {
                    url.searchParams.delete(key);
                }
            });
            activeFilters.forEach(function (filter) {
                url.searchParams.append('filters[]', filter);
            });
            history.replaceState(null, '', url.toString());
        }

        // Forms that reload the page (exclusive/schedule) carry the current
        // filters as hidden inputs; keep them in sync with client toggles.
        function syncFilterInputs() {
            document.querySelectorAll('[data-comics-filter-form]').forEach(function (form) {
                form.querySelectorAll('input[data-comics-filter-input]').forEach(function (input) {
                    input.remove();
                });
                activeFilters.forEach(function (filter) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'filters[]';
                    input.value = filter;
                    input.setAttribute('data-comics-filter-input', '');
                    form.appendChild(input);
                });
            });
        }

        function updateFilterButtons() {
            document.querySelectorAll('[data-comics-filter]').forEach(function (button) {
                var active = activeFilters.has(button.getAttribute('data-comics-filter'));
                button.classList.toggle('admin-btn-success', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            var all = document.querySelector('[data-comics-filter-all]');
            if (all) {
                var none = activeFilters.size === 0;
                all.classList.toggle('admin-btn-success', none);
                all.setAttribute('aria-pressed', none ? 'true' : 'false');
            }
        }

        function updateComicsHeading() {
            var heading = document.querySelector('[data-comics-heading]');
            if (!heading) {
                return;
            }
            if (activeFilters.size === 0) {
                heading.textContent = 'Todos os comics';
                return;
            }
            heading.textContent = 'Comics: ' + Array.from(activeFilters).map(function (filter) {
                return filterLabels[filter] || filter;
            }).join(' + ');
        }

        function refreshComicsCount() {
            var table = comicsTable();
            var count = document.querySelector('[data-comics-count]');
            if (!table || !count) {
                return;
            }
            count.textContent = '(' + table.rows({ search: 'applied' }).count() + ')';
        }

        // Changing a filter goes back to the first page.
        function applyComicsFilters() {
            updateFilterButtons();
            updateComicsHeading();
            syncFiltersUrl();
            syncFilterInputs();
            var table = comicsTable();
            if (table) {
                table.draw();
            }
            refreshComicsCount();
            refreshSelection();
        }

        document.querySelectorAll('[data-comics-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                var filter = button.getAttribute('data-comics-filter');
                if (activeFilters.has(filter)) {
                    activeFilters.delete(filter);
                } else {
                    activeFilters.add(filter);
                }
                applyComicsFilters();
            });
        });

        var filterAll = document.querySelector('[data-comics-filter-all]');
        if (filterAll) {
            filterAll.addEventListener('click', function () {
                activeFilters.clear();
                applyComicsFilters();
            });
        }

        var flashTimer = null;

        function showComicsFlash(message, isError) {
            var flash = document.querySelector('[data-comics-flash]');
            var text = document.querySelector('[data-comics-flash-text]');
            if (!flash || !text) {
                return;
            }
            text.textContent = message;
            flash.hidden = false;
            flash.classList.toggle('border-red-500/30', !!isError);
            flash.classList.toggle('bg-red-500/10', !!isError);
            flash.classList.toggle('text-red-700', !!isError);
            flash.classList.toggle('dark:text-red-200', !!isError);
            flash.classList.toggle('border-emerald-500/30', !isError);
            flash.classList.toggle('bg-emerald-500/10', !isError);
            flash.classList.toggle('text-emerald-700', !isError);
            flash.classList.toggle('dark:text-emerald-200', !isError);
            if (flashTimer) {
                clearTimeout(flashTimer);
            }
            flashTimer = setTimeout(function () { flash.hidden = true; }, 5000);
        }

        $(document).ready(function() {
            var table = $('#comicsTable').DataTable({
                "autoWidth": false,
                // Criado em is the 5th column, or the 6th with the select column.
                "order": [[transferConfigured ? 5 : 4, "desc"]],
                // Keep the page after a full reload (exclusive/schedule forms).
                "stateSave": true
            });

            table.on('draw', function () {
                refreshComicsCount();
            });

            updateFilterButtons();
            updateComicsHeading();
            syncFiltersUrl();
            syncFilterInputs();
            refreshComicsCount();
            refreshSelection();
        });

        // Delete in place: fetch + row.remove() keeps the DataTables page,
        // the search box and the attention filters untouched.
        document.querySelectorAll('[data-comic-delete]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                // The inline onsubmit confirm can still cancel it.
                if (event.defaultPrevented) {
                    return;
                }
                var table = comicsTable();
                if (!table) {
                    return;
                }
                event.preventDefault();

                var button = form.querySelector('button[type="submit"]');
                var rowNode = form.closest('tr');
                var label = button ? button.textContent : '';
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Excluindo…';
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: new FormData(form),
                })
                .then(function (response) {
                    return response.json()
                        .catch(function () { return {}; })
                        .then(function (body) { return { ok: response.ok, body: body }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.ok) {
                        if (button) {
                            button.disabled = false;
                            button.textContent = label;
                        }
                        showComicsFlash(result.body.message || 'Não foi possível excluir o comic.', true);
                        return;
                    }
                    table.row(rowNode).remove().draw(false);
                    showComicsFlash(result.body.message || 'Comic excluído.');
                    refreshSelection();
                })
                .catch(function () {
                    if (button) {
                        button.disabled = false;
                        button.textContent = label;
                    }
                    showComicsFlash('Falha de rede ao excluir o comic.', true);
                });
            });
        });

        function rowLabel(id) {
            return document.querySelector('[data-transfer-status="' + id + '"]');
        }

        function resultHtml(body) {
            return 'concluído (' + body.done + '/' + body.total + ')' +
                (body.url ? ' · <a class="underline" target="_blank" rel="noopener" href="' + body.url + '">abrir no remoto ↗</a>' : '');
        }

        // Poll the push endpoint until the remote finalize answers.
        function pollTransfer(url, label, onDone) {
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (res) {
                if (!res.ok) {
                    onDone(false, res.body.message || ('erro ' + res.status));
                    return;
                }
                if (res.body.complete) {
                    onDone(true, res.body);
                    return;
                }
                if (label) {
                    label.textContent = 'enviando ' + res.body.done + '/' + res.body.total + '…';
                }
                setTimeout(function () { pollTransfer(url, label, onDone); }, 800);
            })
            .catch(function () { onDone(false, 'erro de rede'); });
        }

        // Start (or resume) one comic and follow its chunks. Calls
        // onDone(ok, payloadOrMessage, alreadyDone).
        function startTransfer(id, label, onDone) {
            fetch(transferStartUrl.replace('__ID__', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, status: r.status, body: b }; }); })
            .then(function (res) {
                if (res.status === 409) {
                    onDone(false, res.body.message || 'Já transferido.', true);
                    return;
                }
                if (!res.ok || !res.body.push_url) {
                    onDone(false, res.body.message || ('erro ' + res.status));
                    return;
                }
                pollTransfer(res.body.push_url, label, onDone);
            })
            .catch(function () { onDone(false, 'erro de rede'); });
        }

        // Per-comic button: start, then poll until done.
        document.querySelectorAll('[data-transfer]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-transfer');
                var label = rowLabel(id);
                btn.disabled = true;
                if (label) {
                    label.textContent = 'criando no remoto…';
                }
                startTransfer(id, label, function (ok, body, already) {
                    if (!ok) {
                        btn.disabled = false;
                        if (label) {
                            label.textContent = already ? body : 'erro: ' + body + ' — clique para tentar de novo';
                        }
                        return;
                    }
                    if (label) {
                        label.innerHTML = resultHtml(body);
                    }
                });
            });
        });

        // Batch selection: only comics without a finished transfer get a
        // checkbox, so "select all" never re-sends what already went out.
        var selectAll = document.querySelector('[data-comics-select-all]');
        var batchButton = document.querySelector('[data-comics-batch]');
        var batchStatus = document.querySelector('[data-comics-batch-status]');
        var selectedCount = document.querySelector('[data-comics-selected]');
        var batchRunning = false;
        var batchStop = false;

        function allChecks() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-comic-check]'));
        }

        // "Todos os listados" follows the DataTables search, when in use.
        function listedChecks() {
            var all = allChecks();
            if (!window.jQuery || !$.fn.dataTable || !$.fn.dataTable.isDataTable('#comicsTable')) {
                return all;
            }
            var nodes = $('#comicsTable').DataTable().rows({ search: 'applied' }).nodes();
            return Array.prototype.slice.call(nodes)
                .map(function (row) { return row.querySelector ? row.querySelector('[data-comic-check]') : null; })
                .filter(function (check) { return check !== null; });
        }

        function selectedIds() {
            return allChecks().filter(function (check) { return check.checked; })
                .map(function (check) { return check.value; });
        }

        function refreshSelection() {
            var selected = selectedIds().length;
            if (selectedCount) {
                selectedCount.textContent = selected + ' selecionado(s)';
            }
            if (batchButton) {
                batchButton.disabled = selected === 0;
            }
            if (selectAll) {
                var listed = listedChecks();
                var checked = listed.filter(function (check) { return check.checked; }).length;
                selectAll.disabled = batchRunning || listed.length === 0;
                selectAll.checked = listed.length > 0 && checked === listed.length;
                selectAll.indeterminate = checked > 0 && checked < listed.length;
            }
        }

        if (selectAll && batchButton) {
            allChecks().forEach(function (check) { check.addEventListener('change', refreshSelection); });

            selectAll.addEventListener('change', function () {
                listedChecks().forEach(function (check) { check.checked = selectAll.checked; });
                refreshSelection();
            });

            // Browser-driven batch: one comic at a time, polling each chunk.
            // Clicking again while it runs stops after the current comic.
            batchButton.addEventListener('click', function () {
                if (batchRunning) {
                    batchStop = true;
                    batchButton.textContent = 'Parando…';
                    return;
                }

                var ids = selectedIds();
                if (ids.length === 0) {
                    return;
                }

                batchRunning = true;
                batchStop = false;
                var index = 0;
                var sent = 0;
                var failures = 0;
                batchButton.textContent = 'Parar envio';
                refreshSelection();

                function sendNext() {
                    if (batchStop || index >= ids.length) {
                        batchRunning = false;
                        batchButton.textContent = 'Enviar selecionados p/ remoto';
                        if (batchStatus) {
                            batchStatus.textContent = batchStop
                                ? 'Envio pausado — ' + (ids.length - index) + ' restante(s).'
                                : 'Envio concluído: ' + sent + ' comic(s)' +
                                    (failures > 0 ? ' · ' + failures + ' com erro' : '') + '.';
                        }
                        refreshSelection();
                        return;
                    }

                    var id = ids[index++];
                    var label = rowLabel(id);
                    var rowButton = document.querySelector('[data-transfer="' + id + '"]');
                    if (rowButton) {
                        rowButton.disabled = true;
                        rowButton.textContent = 'Enviando…';
                    }
                    if (label) {
                        label.textContent = 'criando no remoto…';
                    }
                    if (batchStatus) {
                        batchStatus.textContent = 'Enviando ' + index + '/' + ids.length + '…';
                    }

                    startTransfer(id, label, function (ok, body, already) {
                        var check = document.querySelector('[data-comic-check][value="' + id + '"]');

                        if (!ok && !already) {
                            failures++;
                            if (label) {
                                label.textContent = 'erro: ' + body;
                            }
                            if (rowButton) {
                                rowButton.disabled = false;
                                rowButton.textContent = 'Tentar de novo';
                            }
                        } else {
                            sent++;
                            if (check) {
                                check.checked = false;
                            }
                            if (label) {
                                if (already) {
                                    label.textContent = 'já no remoto';
                                } else {
                                    label.innerHTML = resultHtml(body);
                                }
                            }
                            if (rowButton) {
                                rowButton.textContent = already ? 'No remoto' : 'Enviado';
                            }
                        }

                        setTimeout(sendNext, 300);
                    });
                }

                sendNext();
            });

            refreshSelection();
        }
    </script>
@endpush
