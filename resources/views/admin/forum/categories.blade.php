@extends('layouts.admin-layout')

@section('title', 'Categorias do fórum')
@section('page-title', 'Categorias do fórum')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm opacity-70">Seções e quadros. Duas colunas: seções contêm quadros.</p>
        <a href="{{ route('admin.forum.categories.create') }}" class="admin-btn admin-btn-primary">Nova categoria</a>
    </div>

    <p class="mb-2 text-xs opacity-60">Arraste pelo ⋮⋮ para reordenar seções e quadros. A ordem salva automaticamente.</p>

    <div id="section-list" data-reorder-url="{{ route('admin.forum.categories.reorder') }}">
    @foreach ($sections as $section)
        <div class="admin-card mb-4" data-id="{{ $section->id }}">
            <div class="admin-card-header">
                <span class="flex items-center gap-2">
                    <span class="drag-handle cursor-move select-none text-zinc-500" title="Arrastar para reordenar">⋮⋮</span>
                    {{ $section->icon }} {{ $section->name }}
                </span>
                <span class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.forum.categories.toggle', $section->id) }}" class="inline">@csrf
                        <input type="hidden" name="field" value="is_visible">
                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">{{ $section->is_visible ? 'Ocultar' : 'Mostrar' }}</button>
                    </form>
                    <a href="{{ route('admin.forum.categories.edit', $section->id) }}" class="admin-btn admin-btn-sm admin-btn-secondary">Editar</a>
                    <form method="POST" action="{{ route('admin.forum.categories.destroy', $section->id) }}" class="inline" onsubmit="return confirm('Remover esta seção e seus quadros?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Excluir</button>
                    </form>
                </span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table-compact">
                    <thead><tr><th class="w-8"></th><th>Quadro</th><th>Tópicos</th><th>Ordem</th><th>Permissão</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody class="board-list">
                        @forelse ($section->children as $board)
                            <tr data-id="{{ $board->id }}">
                                <td class="drag-handle cursor-move select-none text-zinc-500" title="Arrastar para reordenar">⋮⋮</td>
                                <td>
                                    <span class="flex items-center gap-2">
                                        @if ($board->banner_image_path)
                                            <img src="{{ asset($board->banner_image_path) }}" alt="" class="h-8 w-14 rounded border border-zinc-700 object-cover"
                                                 style="object-position: {{ $board->bannerPosition() }};">
                                        @endif
                                        <span>{{ $board->icon }} {{ $board->name }}<br><span class="text-xs opacity-60">{{ $board->slug }}</span></span>
                                    </span>
                                </td>
                                <td>{{ $board->threads_count }}</td>
                                <td>{{ $board->sort_order }}</td>
                                <td>{{ \App\Models\ForumCategory::roles()[$board->min_role] ?? $board->min_role }}</td>
                                <td>
                                    <span class="admin-badge {{ $board->is_visible ? 'admin-badge-secondary' : 'admin-badge-secondary' }}">{{ $board->is_visible ? 'visível' : 'oculto' }}</span>
                                    @if ($board->is_locked)<span class="admin-badge admin-badge-secondary">trancado</span>@endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.forum.categories.toggle', $board->id) }}" class="inline">@csrf
                                        <input type="hidden" name="field" value="is_visible">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">{{ $board->is_visible ? 'Ocultar' : 'Mostrar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.forum.categories.toggle', $board->id) }}" class="inline">@csrf
                                        <input type="hidden" name="field" value="is_locked">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">{{ $board->is_locked ? 'Destrancar' : 'Trancar' }}</button>
                                    </form>
                                    <a href="{{ route('admin.forum.categories.edit', $board->id) }}" class="admin-btn admin-btn-sm admin-btn-primary">Editar</a>
                                    <form method="POST" action="{{ route('admin.forum.categories.destroy', $board->id) }}" class="inline" onsubmit="return confirm('Remover este quadro?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center opacity-60">Nenhum quadro nesta seção.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
    </div>

    @if ($sections->isEmpty())
        <div class="admin-card"><div class="admin-card-body text-center opacity-60">Nenhuma seção criada ainda. Crie a primeira categoria.</div></div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.getElementById('section-list');
        if (!list || !window.Sortable) return;

        const url = list.dataset.reorderUrl;
        const persist = function (ids) {
            if (!ids.length) return;
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ order: ids }),
            }).catch(function () {});
        };
        const idsIn = function (root) {
            return Array.from(root.querySelectorAll(':scope > [data-id]')).map(function (el) {
                return Number(el.dataset.id);
            });
        };

        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () { persist(idsIn(list)); },
        });

        document.querySelectorAll('.board-list').forEach(function (tbody) {
            new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function () { persist(idsIn(tbody)); },
            });
        });
    });
</script>
@endpush
