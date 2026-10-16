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
            <table id="comicsTable" class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Autor</th>
                        <th>Slug</th>
                        <th>Criado em</th>
                        <th>Visualizações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comics as $comic)
                        <tr>
                            <td>{{ $comic->id }}</td>
                            <td class="font-medium">{{ $comic->title }}</td>
                            <td>{{ $comic->author }}</td>
                            <td><code>{{ $comic->slug }}</code></td>
                            <td class="whitespace-nowrap">{{ $comic->created_at }}</td>
                            <td>{{ $comic->view_count }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('comics.edit', $comic->id) }}" class="admin-btn admin-btn-sm">Editar</a>
                                <a href="{{ route('admin.comics.package', $comic) }}" class="admin-btn admin-btn-sm">Exportar</a>
                                @if ($transferConfigured ?? false)
                                    @if ($comic->transfer?->isDone() && $comic->transfer->remote_view_url)
                                        <a href="{{ $comic->transfer->remote_view_url }}" target="_blank" rel="noopener" class="admin-btn admin-btn-sm" title="Comic {{ $comic->transfer->remote_comic_id }} no remoto">✓ No remoto ↗</a>
                                    @else
                                        <button type="button" data-transfer="{{ $comic->id }}"
                                                data-transfer-url="{{ route('admin.comics.transfer', $comic) }}"
                                                class="admin-btn admin-btn-success admin-btn-sm">
                                            {{ $comic->transfer ? 'Continuar envio ('.min($comic->transfer->next_page - 1, $comic->transfer->total_pages).'/'.$comic->transfer->total_pages.')' : 'Enviar p/ remoto' }}
                                        </button>
                                    @endif
                                @endif
                                <form action="{{ route('admin.comics.destroy', $comic) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Excluir &quot;{{ $comic->title }}&quot; com todas as páginas e arquivos? Isso não pode ser desfeito.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Excluir</button>
                                </form>
                                <span data-transfer-status="{{ $comic->id }}" class="ml-1 text-xs opacity-70"></span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#comicsTable').DataTable({
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
@endsection
