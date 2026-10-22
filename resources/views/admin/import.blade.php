@extends('layouts.admin-layout')

@section('title', 'Importar galeria')
@section('page-title', 'Importar galeria')

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
        <div class="admin-card mb-6 max-w-3xl" id="import-search">
            <div class="admin-card-header">
                <span>1 · Buscar galerias</span>
            </div>
            <div class="admin-card-body">
                <p class="mb-4 text-sm opacity-80">
                    Pesquise por título, artista ou tags e selecione várias galerias para baixar em fila.
                    Também aceita a URL de uma busca do E-Hentai (com <code>f_search</code> e <code>next</code>),
                    para abrir direto naquela página de resultados.
                </p>
                <form method="GET" action="{{ route('admin.import.search') }}" class="flex flex-wrap gap-2">
                    <input type="text" name="q" required maxlength="500" value="{{ old('q') }}"
                           placeholder="ex.: language:portuguese$ furry"
                           class="admin-input min-w-0 flex-1">
                    <button type="submit" class="admin-btn admin-btn-success">Buscar galerias</button>
                </form>
                @error('q')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="admin-card mb-6 max-w-3xl" id="import-gallery">
            <div class="admin-card-header">
                <span>2 · Buscar galeria</span>
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

        @php
            $queueLabels = [
                'pending' => 'Pendente',
                'processing' => 'Baixando',
                'done' => 'Concluída',
                'failed' => 'Falhou',
                'skipped' => 'Já importada',
            ];
            $queueBadges = [
                'pending' => 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-200',
                'processing' => 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-200',
                'done' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
                'failed' => 'bg-red-500/15 text-red-700 dark:text-red-200',
                'skipped' => 'bg-amber-500/15 text-amber-800 dark:text-amber-100',
            ];
        @endphp
        <div class="admin-card mb-6 max-w-3xl" id="import-queue"
             data-queue-process-url="{{ route('admin.import.queue.process') }}"
             data-queue-remove-url="{{ route('admin.import.queue.remove', ['item' => '__ID__']) }}"
             data-queue-retry-url="{{ route('admin.import.queue.retry', ['item' => '__ID__']) }}"
             data-queue-order-url="{{ route('admin.import.queue.reorder') }}">
            <div class="admin-card-header">
                <span>Fila de importação</span>
                <span data-queue-counts class="text-xs font-normal opacity-60">
                    {{ $importQueue['pending'] }} pendente(s) · {{ $importQueue['processing'] }} baixando · {{ $importQueue['done'] }} concluída(s)@if ($importQueue['failed'] > 0) · {{ $importQueue['failed'] }} com falha @endif
                </span>
            </div>
            <div class="admin-card-body text-sm">
                <div id="import-queue-list">
                    @if ($importQueue['total'] === 0)
                        <p class="opacity-60">Nenhuma galeria na fila. Busque acima, cole uma URL ou escolha um comic já no site para começar.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($importQueue['items'] as $item)
                                <li class="flex flex-wrap items-start gap-3" data-queue-item="{{ $item['id'] }}">
                                    <span data-queue-drag draggable="true"
                                          class="cursor-grab select-none px-1 text-xl leading-none opacity-40 hover:opacity-80"
                                          title="Arraste para reordenar" aria-hidden="true">⠿</span>
                                    @if ($item['thumb'])
                                        <img src="{{ $item['thumb'] }}" alt="" loading="lazy" class="h-16 w-12 shrink-0 rounded object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                            <span class="font-medium break-words">{{ $item['title'] }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $queueBadges[$item['status']] ?? '' }}">
                                                {{ $queueLabels[$item['status']] ?? $item['status'] }}
                                            </span>
                                            @if ($item['redownload'])
                                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:text-amber-100"
                                                      title="As páginas atuais serão apagadas e baixadas de novo">
                                                    ↻ tudo de novo
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs opacity-60">
                                            @if ($item['total'] > 0)
                                                {{ $item['done'] }}/{{ $item['total'] }} páginas
                                            @elseif ($item['status'] === 'processing')
                                                preparando…
                                            @endif
                                            @if ($item['progress_url'])
                                                · <a href="{{ $item['progress_url'] }}" class="text-indigo-600 hover:underline dark:text-indigo-300">abrir importação</a>
                                            @endif
                                            · <a href="{{ $item['source_url'] }}" target="_blank" rel="noopener" class="hover:underline">galeria ↗</a>
                                        </p>
                                        @if ($item['error'])
                                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">{{ $item['error'] }}</p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        @if ($item['status'] === 'failed')
                                            <form method="POST" action="{{ route('admin.import.queue.retry', $item['id']) }}">
                                                @csrf
                                                <button type="submit" class="admin-btn admin-btn-sm">Tentar de novo</button>
                                            </form>
                                        @endif
                                        @if ($item['status'] !== 'processing')
                                            <form method="POST" action="{{ route('admin.import.queue.remove', $item['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="admin-btn admin-btn-sm">Remover</button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" id="import-queue-process" class="admin-btn admin-btn-success admin-btn-sm"
                            @disabled($importQueue['pending'] === 0 && $importQueue['processing'] === 0)>
                        {{ $importQueue['processing'] > 0 ? 'Continuar fila' : 'Processar fila' }}
                    </button>
                    @if ($importQueue['done'] + $importQueue['skipped'] > 0)
                        <form method="POST" action="{{ route('admin.import.queue.clear') }}">
                            @csrf
                            <button type="submit" class="admin-btn admin-btn-sm">Limpar concluídas</button>
                        </form>
                    @endif
                    <span id="import-queue-status" class="text-xs opacity-70"></span>
                </div>
                <p class="mt-2 text-xs opacity-60">
                    A fila baixa uma galeria por vez enquanto esta página estiver aberta. Fechar a página
                    pausa; <strong>Processar fila</strong> continua de onde parou. Arraste as linhas pelo
                    <span aria-hidden="true">⠿</span> para mudar a ordem.
                </p>

                <div class="mt-4 grid gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <details>
                        <summary class="cursor-pointer opacity-80 hover:opacity-100">Adicionar por URL</summary>
                        <form method="POST" action="{{ route('admin.import.queue.add') }}" class="mt-2 flex flex-wrap gap-2">
                            @csrf
                            <input type="text" name="url" required maxlength="500"
                                   placeholder="https://e-hentai.org/g/…/…/"
                                   class="admin-input min-w-0 flex-1">
                            <button type="submit" class="admin-btn admin-btn-sm">Adicionar ao fim da fila</button>
                        </form>
                        @error('url')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs opacity-60">
                            Aceita a URL da galeria ou de uma página dela. Galerias já importadas precisam ser
                            adicionadas em “Adicionar comics já no site”.
                        </p>
                    </details>

                    <details>
                        <summary class="cursor-pointer opacity-80 hover:opacity-100">Adicionar comics já no site</summary>
                        <form method="POST" action="{{ route('admin.import.queue.add-comic') }}" class="mt-2 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="comic_id" list="import-queue-comics" required
                                   placeholder="ID ou título do comic" class="admin-input w-40">
                            <datalist id="import-queue-comics">
                                @foreach ($queueComics as $queueComic)
                                    <option value="{{ $queueComic->id }}">{{ $queueComic->title }}</option>
                                @endforeach
                            </datalist>
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="redownload" value="1" class="rounded">
                                Baixar todas as páginas de novo
                            </label>
                            <button type="submit" class="admin-btn admin-btn-sm">Adicionar ao fim da fila</button>
                        </form>
                        @error('comic_id')<p class="mt-1 w-full text-xs text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs opacity-60">
                            Sem a opção marcada, a fila baixa só as páginas pendentes/falhas. Com ela marcada,
                            as páginas atuais são apagadas e a galeria inteira é baixada outra vez.
                        </p>
                    </details>
                </div>
            </div>
        </div>

        @if ($queue->isNotEmpty())
            <div class="admin-card mb-6 max-w-2xl">
                <div class="admin-card-header">
                    <span>Fila de agendados</span>
                </div>
                <div class="admin-card-body text-sm">
                    <ul class="space-y-3">
                        @foreach ($queue as $item)
                            @php($expected = $item->ehImport?->total_pages ?? $item->pages_count)
                            @php($missing = max(0, $expected - $item->pages_count))
                            @php($pct = $expected > 0 ? (int) round($item->pages_count / $expected * 100) : 100)
                            <li>
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <a href="{{ route('admin.import.progress', $item) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-300">
                                        {{ $item->title }}
                                    </a>
                                    <span class="text-xs opacity-60">{{ $item->published_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div class="h-full rounded-full {{ $missing > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs tabular-nums opacity-70">{{ $item->pages_count }}/{{ $expected }}</span>
                                </div>
                                @if ($missing > 0)
                                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-300">
                                        Faltam {{ $missing }} página(s)
                                        @if ($item->ehImport)
                                            · <a href="{{ route('admin.import.progress', $item) }}" class="underline">continuar import</a>
                                        @endif
                                    </p>
                                @endif
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
                        @php($settingType = $def['type'] ?? 'string')
                        @php($settingValue = config("ehimport.{$key}"))
                        <div class="{{ in_array($settingType, ['string', 'text'], true) ? 'sm:col-span-2' : '' }}">
                            <label for="setting-{{ $key }}" class="admin-label">{{ $def['label'] }}</label>
                            @if ($settingType === 'int')
                                <input type="number" class="admin-input" name="{{ $key }}" id="setting-{{ $key }}"
                                       min="{{ $def['min'] }}" max="{{ $def['max'] }}" step="1"
                                       value="{{ old($key, $settingValue) }}">
                            @elseif ($settingType === 'text')
                                <textarea class="admin-input" name="{{ $key }}" id="setting-{{ $key }}" rows="4"
                                          maxlength="{{ $def['maxlen'] ?? 500 }}"
                                          placeholder="Um texto por linha">{{ old($key, is_array($settingValue) ? implode("\n", $settingValue) : $settingValue) }}</textarea>
                            @else
                                <input type="text" class="admin-input" name="{{ $key }}" id="setting-{{ $key }}"
                                       maxlength="{{ $def['maxlen'] ?? 500 }}" placeholder="(vazio = direto, sem proxy)"
                                       value="{{ old($key, $settingValue) }}">
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

    @if ($stage === 'search')
        <div class="admin-card mb-6 max-w-4xl">
            <div class="admin-card-header">
                <span>Buscar galerias</span>
                <span class="text-xs font-normal opacity-60">
                    @if ($foundCount !== null)
                        ~{{ number_format($foundCount, 0, ',', '.') }} resultado(s) ·
                    @endif
                    página {{ $page }}
                </span>
            </div>
            <div class="admin-card-body">
                <form method="GET" action="{{ route('admin.import.search') }}" class="flex flex-wrap gap-2">
                    <input type="text" name="q" required maxlength="500" value="{{ $searchQuery }}"
                           class="admin-input min-w-0 flex-1">
                    <button type="submit" class="admin-btn admin-btn-success">Buscar</button>
                    <a href="{{ route('admin.import') }}" class="admin-btn">Voltar</a>
                </form>
                <p class="mt-1 text-xs opacity-60">
                    Busca livre ou URL de busca do E-Hentai. Os links de paginação preservam o
                    <code>f_search</code> e o cursor (<code>next</code>/<code>prev</code>).
                </p>

                @if ($searchError !== null)
                    <div class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-200">
                        {{ $searchError }}
                    </div>
                @elseif ($results === [])
                    <p class="mt-4 text-sm opacity-70">Nenhuma galeria encontrada para “{{ $searchQuery }}”.</p>
                @else
                    <form method="POST" action="{{ route('admin.import.queue.store') }}" class="mt-4" data-search-results>
                        @csrf
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-xs opacity-70">
                                <input type="checkbox" data-queue-select-all class="rounded">
                                Selecionar todas
                            </label>
                            <span class="text-xs opacity-60" data-queue-selected>0 selecionada(s)</span>
                        </div>
                        <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($results as $item)
                                @php($imported = $importedGids[$item['gid']] ?? null)
                                @php($queued = $queuedGids[$item['gid']] ?? null)
                                <li class="flex items-start gap-3 py-3">
                                    <div class="mt-1 shrink-0">
                                        @if ($imported || $queued)
                                            <input type="checkbox" class="mt-0.5 rounded" disabled
                                                   aria-label="{{ $item['title'] }}">
                                        @else
                                            <input type="checkbox" class="mt-0.5 rounded" data-queue-check
                                                   name="items[]" value="{{ $item['gid'] }}:{{ $item['token'] }}"
                                                   aria-label="Selecionar {{ $item['title'] }}">
                                        @endif
                                    </div>
                                    @if ($item['thumb'])
                                        <img src="{{ $item['thumb'] }}" alt="" loading="lazy" class="h-20 w-14 shrink-0 rounded object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1 text-sm">
                                        <a href="{{ $item['source_url'] }}" target="_blank" rel="noopener"
                                           class="font-medium break-words text-indigo-600 hover:underline dark:text-indigo-300">{{ $item['title'] }}</a>
                                        <p class="mt-0.5 text-xs opacity-60">
                                            {{ $item['category'] ?? '—' }}
                                            @if ($item['pages']) · {{ $item['pages'] }} página(s) @endif
                                            @if ($item['posted']) · {{ $item['posted'] }} @endif
                                            @if ($item['uploader']) · por {{ $item['uploader'] }} @endif
                                        </p>
                                        @if ($imported)
                                            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-200">
                                                Já importado
                                                @if ($imported['title'] !== '')
                                                    como <a href="{{ route('comics.showById', $imported['comic_id']) }}" class="underline">{{ $imported['title'] }}</a>
                                                @endif
                                            </p>
                                        @elseif ($queued)
                                            <p class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">
                                                Na fila ({{ ['pending' => 'pendente', 'processing' => 'baixando', 'done' => 'concluída', 'failed' => 'falhou', 'skipped' => 'já importada'][$queued] ?? $queued }})
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="submit" class="admin-btn admin-btn-success" data-queue-submit disabled>
                                Adicionar selecionadas à fila
                            </button>
                            <a href="{{ route('admin.import') }}" class="admin-btn admin-btn-sm">Voltar</a>
                        </div>
                    </form>

                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-800">
                        <a href="{{ route('admin.import.search', ['q' => $searchQuery]) }}" class="admin-btn admin-btn-sm">« Primeira</a>
                        @if ($prevCursor !== null)
                            <a href="{{ route('admin.import.search', array_filter(['q' => $searchQuery, 'prev' => $prevCursor, 'page' => max(1, $page - 1)])) }}" class="admin-btn admin-btn-sm">‹ Anterior</a>
                        @else
                            <span class="admin-btn admin-btn-sm pointer-events-none opacity-40">‹ Anterior</span>
                        @endif
                        <span class="text-xs opacity-60">Página {{ $page }}</span>
                        @if ($nextCursor !== null)
                            <a href="{{ route('admin.import.search', array_filter(['q' => $searchQuery, 'next' => $nextCursor, 'page' => $page + 1])) }}" class="admin-btn admin-btn-sm">Próxima ›</a>
                        @else
                            <span class="admin-btn admin-btn-sm pointer-events-none opacity-40">Próxima ›</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($stage === 'edit')
        @if ($duplicateExact !== null)
            <div class="admin-card mb-6 max-w-2xl border-red-500/30 bg-red-500/10">
                <div class="admin-card-body text-sm text-red-700 dark:text-red-200">
                    <p class="font-semibold">⚠️ Esta galeria já foi importada</p>
                    <p class="mt-1">
                        Ela já está no site como
                        <a href="{{ route('comics.showById', $duplicateExact->id) }}" class="font-medium underline">{{ $duplicateExact->title }}</a>.
                    </p>
                    <p class="mt-1 text-xs opacity-80">
                        Para baixar as páginas de novo, abra a importação existente e use “Baixar todas as páginas novamente”.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('comics.showById', $duplicateExact->id) }}" class="admin-btn admin-btn-sm">Ver comic</a>
                        @if ($duplicateExact->ehImport)
                            <a href="{{ route('admin.import.progress', $duplicateExact) }}" class="admin-btn admin-btn-sm">Abrir importação</a>
                        @endif
                    </div>
                </div>
            </div>
        @elseif ($duplicateMatches !== [])
            <div class="admin-card mb-6 max-w-2xl border-amber-500/30 bg-amber-500/10">
                <div class="admin-card-body text-sm text-amber-800 dark:text-amber-100">
                    <p class="font-semibold">⚠️ Possível duplicata</p>
                    <p class="mt-1 text-xs opacity-80">
                        Encontramos comics com título parecido. Confira antes de importar para não publicar o mesmo trabalho duas vezes.
                    </p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($duplicateMatches as $match)
                            @php($matchComic = $match['comic'])
                            <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <a href="{{ route('comics.showById', $matchComic->id) }}" class="font-medium underline">{{ $matchComic->title }}</a>
                                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] font-semibold">{{ $match['score'] }}% · {{ $match['label'] }}</span>
                                @if ($matchComic->author)
                                    <span class="text-xs opacity-70">{{ $matchComic->author }}</span>
                                @endif
                                <span class="text-xs opacity-70">{{ $matchComic->isScheduled() ? 'Agendado' : 'Publicado' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @else
            <div class="admin-card mb-6 max-w-2xl border-emerald-500/30 bg-emerald-500/10">
                <div class="admin-card-body text-sm text-emerald-700 dark:text-emerald-200">
                    ✓ Nenhum comic parecido encontrado no banco.
                </div>
            </div>
        @endif

        <div class="admin-card mb-6 max-w-2xl">
            <div class="admin-card-header">
                <span>3 · Revisar e agendar</span>
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
                           class="text-indigo-600 hover:underline dark:text-indigo-300">abrir galeria ↗</a>
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
                        @include('components.scheduled-date-calendar', [
                            'inputId' => 'import-published',
                            'scheduledDateCounts' => $scheduledDateCounts,
                            'admin' => true,
                        ])
                        <p class="mt-1 text-xs opacity-60">
                            Padrão: um dia após o último agendado da fila (ou amanhã, se a fila estiver vazia).
                            Vazio = publica na hora.
                        </p>
                        @error('published_at')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="admin-btn admin-btn-success" @disabled($duplicateExact !== null)>Criar e baixar páginas</button>
                        <a href="{{ route('admin.import') }}" class="admin-btn">Voltar</a>
                    </div>
                    @if ($duplicateExact !== null)
                        <p class="text-xs text-red-600 dark:text-red-300">
                            Esta galeria já está no banco — use a importação existente para baixar as páginas novamente.
                        </p>
                    @endif
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
                <span>4 · Baixando páginas</span>
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
                    <div id="import-pages" class="mt-4 grid grid-cols-5 gap-1.5 sm:grid-cols-8 lg:grid-cols-10"
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
                    @if ($transferConfigured)
                        @if ($comic->transfer?->isDone() && $comic->transfer->remote_view_url)
                            <a href="{{ $comic->transfer->remote_view_url }}" target="_blank" rel="noopener"
                               class="admin-btn admin-btn-sm" title="Comic {{ $comic->transfer->remote_comic_id }} no remoto">✓ No remoto ↗</a>
                        @else
                            <span id="import-transfer-box" class="inline-flex flex-wrap items-center gap-2">
                                <button type="button"
                                        data-import-transfer="{{ $comic->id }}"
                                        data-transfer-url="{{ route('admin.comics.transfer', $comic) }}"
                                        class="admin-btn admin-btn-success admin-btn-sm">
                                    {{ $comic->transfer ? 'Continuar envio ('.min($comic->transfer->next_page - 1, $comic->transfer->total_pages).'/'.$comic->transfer->total_pages.')' : 'Enviar p/ remoto' }}
                                </button>
                                <span data-transfer-status="{{ $comic->id }}" class="text-xs opacity-70"></span>
                            </span>
                        @endif
                    @endif
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
                        <p id="import-modal-msg" class="mt-2 hidden text-xs"></p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a id="import-modal-download" href="#" data-file-url-template="{{ route('admin.import.page-file', [$comic, '__N__']) }}" class="admin-btn admin-btn-sm">Baixar imagem</a>
                            <button type="button" id="import-modal-browser" data-upload-url-template="{{ route('admin.import.page-upload', [$comic, '__N__']) }}" data-proxy-base="{{ config('ehimport.image_proxy') }}" class="admin-btn admin-btn-sm">Baixar pelo navegador</button>
                            <button type="button" id="import-modal-retry" class="admin-btn admin-btn-success admin-btn-sm">Tentar de novo</button>
                        </div>
                        <div class="mt-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                            <p class="mb-1 text-xs opacity-60">Ou salve a imagem no seu PC e anexe aqui (cai no número certo):</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="file" id="import-modal-file" accept="image/*" class="text-xs">
                                <button type="button" id="import-modal-attach" class="admin-btn admin-btn-sm">Anexar arquivo</button>
                            </div>
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

@push('scripts')
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
    var transferBox = document.getElementById('import-transfer-box');
    var statsLine = document.getElementById('import-stats');
    var logBoxes = document.querySelectorAll('[data-import-log]');
    var modal = document.getElementById('import-modal');
    var modalTitle = document.getElementById('import-modal-title');
    var modalThumb = document.getElementById('import-modal-thumb');
    var modalImg = document.getElementById('import-modal-img');
    var modalRows = document.getElementById('import-modal-rows');
    var modalDownload = document.getElementById('import-modal-download');
    var modalBrowser = document.getElementById('import-modal-browser');
    var modalRetry = document.getElementById('import-modal-retry');
    var modalMsg = document.getElementById('import-modal-msg');
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
        modalBrowser.setAttribute('data-number', n);
        modalBrowser.classList.toggle('hidden', (d.status || '') === 'done' || !d.image_url);
        modalMsg.classList.add('hidden');
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
        // Only offer the remote transfer when every page is ready.
        if (transferBox) transferBox.classList.toggle('hidden', fails.length > 0);
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
    modalBrowser.addEventListener('click', function () {
        var n = parseInt(modalBrowser.getAttribute('data-number'), 10);
        var d = lastDetails[n] || {};
        if (!d.image_url) return;
        modalBrowser.disabled = true;
        modalBrowser.textContent = 'Baixando…';
        modalMsg.classList.add('hidden');
        // The browser can reach hath ports the server firewall blocks,
        // but the image host must allow CORS for fetch() to read it.
        function postBlob(blob, where) {
            if (!blob.type.startsWith('image/')) throw new Error('não veio imagem');
            modalBrowser.textContent = 'Enviando (' + where + ')…';
            var fd = new FormData();
            fd.append('image', blob, 'page-' + n + '.jpg');
            return fetch(modalBrowser.getAttribute('data-upload-url-template').replace('__N__', n), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            }).then(function (r) {
                if (!r.ok) throw new Error('servidor recusou (' + r.status + ')');
                window.location.reload();
            });
        }
        function fetchDirect() {
            return fetch(d.image_url, { headers: { 'Accept': 'image/*' } }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.blob();
            });
        }
        function fetchProxied() {
            var base = modalBrowser.getAttribute('data-proxy-base') || '';
            if (!base) throw new Error('CORS bloqueado e sem proxy configurado');
            var stripped = d.image_url.replace(/^https?:\/\//i, '');
            return fetch(base + encodeURIComponent(stripped), { headers: { 'Accept': 'image/*' } }).then(function (r) {
                if (!r.ok) throw new Error('proxy HTTP ' + r.status);
                return r.blob();
            });
        }
        fetchDirect()
            .catch(function () { return fetchProxied(); })
            .then(function (blob) { return postBlob(blob, 'navegador'); })
            .catch(function (err) {
                // fetch() failed: probe with a plain <img> (display needs
                // no CORS) to tell "loads but unreadable" apart from
                // "unreachable", then guide to the manual attach below.
                probeImage(d.image_url, function (loads) {
                    modalBrowser.disabled = false;
                    modalBrowser.textContent = 'Baixar pelo navegador';
                    modalMsg.textContent = loads
                        ? 'A imagem carrega no navegador, mas o host bloqueia a leitura (CORS): ' + (err.message || 'erro') + '. Salve a imagem no PC e anexe abaixo.'
                        : 'Não deu pelo navegador: ' + (err.message || 'erro') + '. Nem carrega aqui — tente o retry do servidor ou salve e anexe.';
                    modalMsg.classList.remove('hidden');
                });
            });
    });

    // Plain <img> load test: display is CORS-free, so onload proves the
    // browser reaches the host even when fetch/read is blocked.
    function probeImage(url, done) {
        var finished = false;
        var img = new Image();
        var timer = setTimeout(function () { finish(false); }, 15000);
        function finish(ok) {
            if (finished) return;
            finished = true;
            clearTimeout(timer);
            img.onload = img.onerror = null;
            done(ok);
        }
        img.onload = function () { finish(true); };
        img.onerror = function () { finish(false); };
        img.src = url;
    }
    var modalFile = document.getElementById('import-modal-file');
    document.getElementById('import-modal-attach').addEventListener('click', function () {
        var n = parseInt(modalRetry.getAttribute('data-number'), 10);
        if (!modalFile.files.length) {
            modalMsg.textContent = 'Escolha o arquivo da página ' + n + ' primeiro.';
            modalMsg.classList.remove('hidden');
            return;
        }
        modalMsg.classList.add('hidden');
        var fd = new FormData();
        fd.append('image', modalFile.files[0]);
        fetch(modalBrowser.getAttribute('data-upload-url-template').replace('__N__', n), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: fd,
        })
        .then(function (r) {
            if (!r.ok) throw new Error('servidor recusou (' + r.status + ')');
            window.location.reload();
        })
        .catch(function (err) {
            modalMsg.textContent = 'Falha ao anexar: ' + (err.message || 'erro');
            modalMsg.classList.remove('hidden');
        });
    });
})();
</script>
@if ($transferConfigured)
<script>
// Send the finished import to the configured remote host (same flow as
// Admin → Comics → Enviar p/ remoto).
(function () {
    var btn = document.querySelector('[data-import-transfer]');
    if (!btn) return;

    var label = document.querySelector('[data-transfer-status="' + btn.getAttribute('data-import-transfer') + '"]');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        if (label) label.textContent = 'criando no remoto…';
        fetch(btn.getAttribute('data-transfer-url'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
        .then(function (res) {
            if (!res.ok || !res.body.push_url) {
                btn.disabled = false;
                if (label) label.textContent = 'erro: ' + (res.body.message || res.status);
                return;
            }
            pollPush(res.body.push_url, label, btn);
        })
        .catch(function () {
            btn.disabled = false;
            if (label) label.textContent = 'erro de rede';
        });
    });

    function pollPush(url, label, btn) {
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
        .then(function (res) {
            if (!res.ok) {
                btn.disabled = false;
                if (label) label.textContent = 'erro: ' + (res.body.message || res.status) + ' — clique para tentar de novo';
                return;
            }
            if (res.body.complete) {
                if (label) {
                    label.innerHTML = 'concluído (' + res.body.done + '/' + res.body.total + ')' +
                        (res.body.url ? ' · <a class="underline" target="_blank" rel="noopener" href="' + res.body.url + '">abrir no remoto ↗</a>' : '');
                }
                return;
            }
            if (label) label.textContent = 'enviando ' + res.body.done + '/' + res.body.total + '…';
            setTimeout(function () { pollPush(url, label, btn); }, 800);
        })
        .catch(function () {
            btn.disabled = false;
            if (label) label.textContent = 'erro de rede — clique para tentar de novo';
        });
    }
})();
</script>
@endif
@endif
@endpush

@push('scripts')
@if ($stage === 'search')
<script>
// Select galleries from the results and count the selection.
(function () {
    var form = document.querySelector('[data-search-results]');
    if (!form) return;
    var checks = Array.prototype.slice.call(form.querySelectorAll('[data-queue-check]'));
    var all = form.querySelector('[data-queue-select-all]');
    var submit = form.querySelector('[data-queue-submit]');
    var count = form.querySelector('[data-queue-selected]');

    function refresh() {
        var selected = checks.filter(function (check) { return check.checked; }).length;
        count.textContent = selected + ' selecionada(s)';
        submit.disabled = selected === 0;
        if (all) {
            all.checked = checks.length > 0 && selected === checks.length;
            all.indeterminate = selected > 0 && selected < checks.length;
        }
    }

    checks.forEach(function (check) { check.addEventListener('change', refresh); });
    if (all) {
        all.addEventListener('change', function () {
            checks.forEach(function (check) { check.checked = all.checked; });
            refresh();
        });
    }
    refresh();
})();
</script>
@endif
@endpush

@push('scripts')
@if ($stage === 'form')
<script>
// Browser-driven bulk queue: walk the pending galleries one by one,
// downloading a chunk of pages per request (same flow as the progress
// view). Closing the tab pauses; the button resumes from the last item.
(function () {
    var root = document.getElementById('import-queue');
    if (!root) return;
    var list = document.getElementById('import-queue-list');
    var button = document.getElementById('import-queue-process');
    var statusEl = document.getElementById('import-queue-status');
    var countsEl = root.querySelector('[data-queue-counts]');
    var processUrl = root.dataset.queueProcessUrl;
    var removeUrl = root.dataset.queueRemoveUrl;
    var retryUrl = root.dataset.queueRetryUrl;
    var csrf = '{{ csrf_token() }}';
    var running = false;

    var labels = { pending: 'Pendente', processing: 'Baixando', done: 'Concluída', failed: 'Falhou', skipped: 'Já importada' };
    var badgeClasses = {
        pending: 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-200',
        processing: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-200',
        done: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
        failed: 'bg-red-500/15 text-red-700 dark:text-red-200',
        skipped: 'bg-amber-500/15 text-amber-800 dark:text-amber-100'
    };

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function actionForm(url, label, method) {
        return '<form method="POST" action="' + esc(url) + '">'
            + '<input type="hidden" name="_token" value="' + esc(csrf) + '">'
            + (method ? '<input type="hidden" name="_method" value="' + method + '">' : '')
            + '<button type="submit" class="admin-btn admin-btn-sm">' + esc(label) + '</button></form>';
    }

    function render(queue) {
        countsEl.textContent = queue.pending + ' pendente(s) · ' + queue.processing + ' baixando · '
            + queue.done + ' concluída(s)' + (queue.failed > 0 ? ' · ' + queue.failed + ' com falha' : '');

        if (!queue.items.length) {
            list.innerHTML = '<p class="opacity-60">Nenhuma galeria na fila. Busque acima, cole uma URL ou escolha um comic já no site para começar.</p>';
            return;
        }

        var html = '<ul class="space-y-3">';
        queue.items.forEach(function (item) {
            var progress = item.total > 0
                ? item.done + '/' + item.total + ' páginas'
                : (item.status === 'processing' ? 'preparando…' : '');

            html += '<li class="flex flex-wrap items-start gap-3" data-queue-item="' + item.id + '">'
                + '<span data-queue-drag draggable="true" class="cursor-grab select-none px-1 text-xl leading-none opacity-40 hover:opacity-80" title="Arraste para reordenar" aria-hidden="true">⠿</span>';
            if (item.thumb) {
                html += '<img src="' + esc(item.thumb) + '" alt="" loading="lazy" class="h-16 w-12 shrink-0 rounded object-cover">';
            }
            html += '<div class="min-w-0 flex-1">'
                + '<div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">'
                + '<span class="font-medium break-words">' + esc(item.title) + '</span>'
                + '<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ' + (badgeClasses[item.status] || '') + '">'
                + esc(labels[item.status] || item.status) + '</span>'
                + (item.redownload ? '<span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:text-amber-100" title="As páginas atuais serão apagadas e baixadas de novo">↻ tudo de novo</span>' : '')
                + '</div>'
                + '<p class="text-xs opacity-60">'
                + (progress ? '<span>' + esc(progress) + '</span>' : '')
                + (item.progress_url ? ' · <a href="' + esc(item.progress_url) + '" class="text-indigo-600 hover:underline dark:text-indigo-300">abrir importação</a>' : '')
                + ' · <a href="' + esc(item.source_url) + '" target="_blank" rel="noopener" class="hover:underline">galeria ↗</a>'
                + '</p>'
                + (item.error ? '<p class="mt-1 text-xs text-amber-600 dark:text-amber-300">' + esc(item.error) + '</p>' : '')
                + '</div>'
                + '<div class="flex shrink-0 gap-1">'
                + (item.status === 'failed' ? actionForm(retryUrl.replace('__ID__', item.id), 'Tentar de novo') : '')
                + (item.status !== 'processing' ? actionForm(removeUrl.replace('__ID__', item.id), 'Remover', 'DELETE') : '')
                + '</div></li>';
        });
        list.innerHTML = html + '</ul>';
    }

    function setStatus(text) {
        statusEl.textContent = text || '';
    }

    // Drag and drop ordering: the handle starts the drag, the rows swap
    // live and the new order is saved when the drag ends.
    var draggedItem = null;
    var orderUrl = root.dataset.queueOrderUrl;

    function rowAfter(y) {
        var rows = Array.prototype.slice.call(list.querySelectorAll('[data-queue-item]'))
            .filter(function (row) { return row !== draggedItem; });

        return rows.reduce(function (closest, row) {
            var box = row.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: row };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    list.addEventListener('dragstart', function (event) {
        var handle = event.target.closest ? event.target.closest('[data-queue-drag]') : null;
        if (!handle) {
            return;
        }
        draggedItem = handle.closest('[data-queue-item]');
        if (!draggedItem) {
            return;
        }
        draggedItem.classList.add('opacity-40');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedItem.getAttribute('data-queue-item'));
        if (event.dataTransfer.setDragImage) {
            event.dataTransfer.setDragImage(draggedItem, 24, 24);
        }
    });

    list.addEventListener('dragover', function (event) {
        if (!draggedItem) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        var ul = list.querySelector('ul');
        if (!ul) {
            return;
        }
        var after = rowAfter(event.clientY);
        if (after === null) {
            ul.appendChild(draggedItem);
        } else {
            ul.insertBefore(draggedItem, after);
        }
    });

    list.addEventListener('dragend', function () {
        if (!draggedItem) {
            return;
        }
        draggedItem.classList.remove('opacity-40');
        draggedItem = null;
        saveOrder();
    });

    function saveOrder() {
        var ids = Array.prototype.slice.call(list.querySelectorAll('[data-queue-item]'))
            .map(function (row) { return parseInt(row.getAttribute('data-queue-item'), 10); });
        if (!orderUrl || ids.length === 0) {
            return;
        }

        fetch(orderUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ ids: ids }),
        })
        .then(function (response) {
            setStatus(response.ok ? 'Ordem da fila salva.' : 'Não foi possível salvar a ordem da fila.');
        })
        .catch(function () {
            setStatus('Não foi possível salvar a ordem da fila (rede).');
        });
    }

    function stop(text) {
        running = false;
        button.textContent = 'Continuar fila';
        setStatus(text);
    }

    function step() {
        if (!running) return;

        fetch(processUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        })
        .then(function (response) {
            var retryAfter = parseInt(response.headers.get('Retry-After') || '0', 10) || 0;
            return response.json().then(function (body) {
                return { ok: response.ok, status: response.status, body: body, retryAfter: retryAfter };
            }, function () {
                return { ok: response.ok, status: response.status, body: {}, retryAfter: retryAfter };
            });
        })
        .then(function (res) {
            if (res.body && res.body.queue) render(res.body.queue);

            if (res.status === 429) {
                setStatus('Limite de downloads atingido — retomando em ' + (res.retryAfter || 60) + 's…');
                setTimeout(step, (res.retryAfter || 60) * 1000);
                return;
            }
            if (res.status === 409) {
                setStatus('Outra aba está baixando — tentando de novo…');
                setTimeout(step, 3000);
                return;
            }
            if (!res.ok) {
                stop('Erro (' + res.status + '): ' + ((res.body && res.body.message) || 'falha ao processar a fila'));
                return;
            }
            if (res.body.done) {
                running = false;
                button.disabled = true;
                button.textContent = 'Fila concluída';
                setStatus('Fila concluída.');
                return;
            }
            if (res.body.comic) {
                setStatus(res.body.comic.title + ' · ' + res.body.comic.done + '/' + res.body.comic.total + ' páginas');
            } else if (res.body.failed) {
                setStatus('Item falhou: ' + (res.body.message || 'erro'));
            } else if (res.body.skipped) {
                setStatus('Item já importado — seguindo para o próximo…');
            }
            setTimeout(step, 700);
        })
        .catch(function () {
            setStatus('Erro de rede — tentando de novo em 5s…');
            setTimeout(step, 5000);
        });
    }

    button.addEventListener('click', function () {
        if (running) {
            running = false;
            button.textContent = 'Continuar fila';
            setStatus('Fila pausada.');
            return;
        }
        running = true;
        button.disabled = false;
        button.textContent = 'Pausar fila';
        setStatus('Iniciando…');
        step();
    });

    list.addEventListener('submit', function (event) {
        if (!event.target.matches('form')) {
            return;
        }
        if (running && !window.confirm('A fila está rodando. Remover/tentar este item agora?')) {
            event.preventDefault();
        }
    });
})();
</script>
@endif
@endpush
