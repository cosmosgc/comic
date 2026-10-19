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

    <div class="admin-card">
        <div class="admin-card-header">
            <span>Todos os comics</span>
        </div>
        <div class="admin-table-wrap">
            <table id="comicsTable" class="admin-table admin-table-compact table-fixed">
                <thead>
                    <tr>
                        <th class="w-[4%]">ID</th>
                        <th class="w-[18%]">Nome</th>
                        <th class="w-[9%]">Autor</th>
                        <th class="w-[12%]">Slug</th>
                        <th class="w-[8%]">Criado em</th>
                        <th class="w-[12%]">Publicação</th>
                        <th class="w-[15%]">Agendar</th>
                        <th class="w-[6%]">Views</th>
                        <th class="w-[16%]">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comics as $comic)
                        <tr>
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
                                <form method="POST" action="{{ route('admin.comics.exclusive', $comic) }}" class="mt-1">
                                    @csrf
                                    <input type="hidden" name="is_exclusive" value="{{ $comic->isExclusive() ? 0 : 1 }}">
                                    <button type="submit"
                                            class="admin-btn admin-btn-sm {{ $comic->isExclusive() ? 'admin-btn-warning' : '' }}"
                                            title="{{ $comic->isExclusive() ? 'Voltar a público: qualquer visitante lê' : 'Só membros logados leem; visitantes veem o card bloqueado' }}">
                                        {{ $comic->isExclusive() ? 'Tornar público' : 'Tornar exclusivo' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.comics.schedule', $comic) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <input type="datetime-local" name="published_at"
                                           value="{{ $comic->published_at?->format('Y-m-d\TH:i') }}"
                                           class="admin-input admin-input-sm min-w-0 max-w-full"
                                           aria-label="Data de publicação de {{ $comic->title }}">
                                    <button type="submit" class="admin-btn admin-btn-sm w-full">Salvar</button>
                                </form>
                                @if ($comic->isScheduled())
                                    <form method="POST" action="{{ route('admin.comics.schedule', $comic) }}" class="mt-1">
                                        @csrf
                                        <input type="hidden" name="published_at" value="">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-success">Publicar agora</button>
                                    </form>
                                @endif
                            </td>
                            <td>{{ $comic->view_count }}</td>
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
                                          onsubmit="return confirm('Excluir &quot;{{ $comic->title }}&quot; com todas as páginas e arquivos? Isso não pode ser desfeito.')">
                                        @csrf
                                        @method('DELETE')
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
        $(document).ready(function() {
            $('#comicsTable').DataTable({
                "autoWidth": false,
                "order": [[4, "desc"]] // Order by Created At (5th column, index 4) in descending order
            });
        });

        // Server-to-server transfer: start per comic, then poll the push
        // endpoint chunk by chunk until the remote finalize answers.
        document.querySelectorAll('[data-transfer]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-transfer');
                var label = document.querySelector('[data-transfer-status="' + id + '"]');
                btn.disabled = true;
                label.textContent = 'criando no remoto…';
                fetch(btn.getAttribute('data-transfer-url'), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                })
                .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                .then(function (res) {
                    if (!res.ok || !res.body.push_url) {
                        btn.disabled = false;
                        label.textContent = 'erro: ' + (res.body.message || res.status);
                        return;
                    }
                    pollPush(res.body.push_url, res.body.total || 0, label, btn);
                })
                .catch(function () {
                    btn.disabled = false;
                    label.textContent = 'erro de rede';
                });
            });
        });

        function pollPush(url, total, label, btn) {
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (res) {
                if (!res.ok) {
                    btn.disabled = false;
                    label.textContent = 'erro: ' + (res.body.message || res.status) + ' — clique para tentar de novo';
                    return;
                }
                if (res.body.complete) {
                    label.innerHTML = 'concluído (' + res.body.done + '/' + res.body.total + ')' +
                        (res.body.url ? ' · <a class="underline" target="_blank" rel="noopener" href="' + res.body.url + '">abrir no remoto ↗</a>' : '');
                    return;
                }
                label.textContent = 'enviando ' + res.body.done + '/' + res.body.total + '…';
                setTimeout(function () { pollPush(url, total, label, btn); }, 800);
            })
            .catch(function () {
                btn.disabled = false;
                label.textContent = 'erro de rede — clique para tentar de novo';
            });
        }
    </script>
@endpush
