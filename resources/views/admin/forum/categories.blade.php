@extends('layouts.admin-layout')

@section('title', 'Categorias do fórum')
@section('page-title', 'Categorias do fórum')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm opacity-70">Seções e quadros. Duas colunas: seções contêm quadros.</p>
        <a href="{{ route('admin.forum.categories.create') }}" class="admin-btn admin-btn-primary">Nova categoria</a>
    </div>

    @foreach ($sections as $section)
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <span>{{ $section->icon }} {{ $section->name }}</span>
                <span class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.forum.categories.toggle', $section->id) }}" class="inline">@csrf
                        <input type="hidden" name="field" value="is_visible">
                        <button class="admin-btn admin-btn-sm admin-btn-secondary">{{ $section->is_visible ? 'Ocultar' : 'Mostrar' }}</button>
                    </form>
                    <a href="{{ route('admin.forum.categories.edit', $section->id) }}" class="admin-btn admin-btn-sm admin-btn-secondary">Editar</a>
                    <form method="POST" action="{{ route('admin.forum.categories.destroy', $section->id) }}" class="inline" onsubmit="return confirm('Remover esta seção e seus quadros?')">
                        @csrf @method('DELETE')
                        <button class="admin-btn admin-btn-sm admin-btn-secondary">Excluir</button>
                    </form>
                </span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table-compact">
                    <thead><tr><th>Quadro</th><th>Tópicos</th><th>Ordem</th><th>Permissão</th><th>Status</th><th>Ações</th></tr></thead>
                    <tbody>
                        @forelse ($section->children as $board)
                            <tr>
                                <td>{{ $board->icon }} {{ $board->name }}<br><span class="text-xs opacity-60">{{ $board->slug }}</span></td>
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
                                        <button class="admin-btn admin-btn-sm admin-btn-secondary">{{ $board->is_visible ? 'Ocultar' : 'Mostrar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.forum.categories.toggle', $board->id) }}" class="inline">@csrf
                                        <input type="hidden" name="field" value="is_locked">
                                        <button class="admin-btn admin-btn-sm admin-btn-secondary">{{ $board->is_locked ? 'Destrancar' : 'Trancar' }}</button>
                                    </form>
                                    <a href="{{ route('admin.forum.categories.edit', $board->id) }}" class="admin-btn admin-btn-sm admin-btn-primary">Editar</a>
                                    <form method="POST" action="{{ route('admin.forum.categories.destroy', $board->id) }}" class="inline" onsubmit="return confirm('Remover este quadro?')">
                                        @csrf @method('DELETE')
                                        <button class="admin-btn admin-btn-sm admin-btn-secondary">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center opacity-60">Nenhum quadro nesta seção.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if ($sections->isEmpty())
        <div class="admin-card"><div class="admin-card-body text-center opacity-60">Nenhuma seção criada ainda. Crie a primeira categoria.</div></div>
    @endif
@endsection
