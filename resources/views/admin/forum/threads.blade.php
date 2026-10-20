@extends('layouts.admin-layout')

@section('title', 'Tópicos do fórum')
@section('page-title', 'Tópicos do fórum')

@section('content')
    <form method="GET" action="{{ route('admin.forum.threads') }}" class="admin-card mb-4">
        <div class="admin-card-body flex flex-wrap items-end gap-3">
            <div>
                <label class="admin-label" for="q">Buscar título</label>
                <input id="q" name="q" value="{{ $query }}" class="admin-input" placeholder="título…">
            </div>
            <div>
                <label class="admin-label" for="category">Quadro</label>
                <select id="category" name="category" class="admin-input">
                    <option value="">Todos</option>
                    @foreach ($boards as $board)
                        <option value="{{ $board->id }}" @selected((int) $category === $board->id)>{{ $board->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="status">Status</label>
                <select id="status" name="status" class="admin-input">
                    <option value="all" @selected($status === 'all')>Todos</option>
                    <option value="active" @selected($status === 'active')>Publicados</option>
                    <option value="pending" @selected($status === 'pending')>Pendentes</option>
                    <option value="removed" @selected($status === 'removed')>Removidos</option>
                </select>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
            @if ($query !== '' || $category || $status !== 'all')
                <a href="{{ route('admin.forum.threads') }}" class="admin-btn admin-btn-secondary">Limpar</a>
            @endif
        </div>
    </form>

    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Título</th><th>Autor</th><th>Quadro</th><th>Respostas</th><th>Status</th><th>Criado</th><th>Ações</th></tr></thead>
                <tbody>
                    @forelse ($threads as $thread)
                        <tr>
                            <td class="max-w-xs">
                                <span class="flex items-center gap-2">
                                    @if ($thread->banner_image_path)
                                        <img src="{{ asset($thread->banner_image_path) }}" alt="" class="h-8 w-14 shrink-0 rounded border border-zinc-700 object-cover">
                                    @endif
                                    <span class="truncate">{{ $thread->title }}</span>
                                </span>
                            </td>
                            <td>{{ $thread->author?->name ?? '—' }}</td>
                            <td>{{ $thread->category?->name ?? '—' }}</td>
                            <td>{{ $thread->reply_count }}</td>
                            <td class="whitespace-nowrap">
                                @if ($thread->is_pinned)<span class="admin-badge admin-badge-info">fixado</span>@endif
                                @if ($thread->is_locked)<span class="admin-badge admin-badge-warning">trancado</span>@endif
                                @if (! $thread->is_approved)<span class="admin-badge admin-badge-danger">pendente</span>@endif
                                @if ($thread->trashed())<span class="admin-badge admin-badge-danger">removido</span>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $thread->created_at->diffForHumans() }}</td>
                            <td class="whitespace-nowrap">
                                @if ($thread->trashed())
                                    <form method="POST" action="{{ route('admin.forum.threads.restore', $thread->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Restaurar</button>
                                    </form>
                                @else
                                    <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}" target="_blank" rel="noopener" class="admin-btn admin-btn-sm admin-btn-secondary">Ver</a>
                                    <a href="{{ route('admin.forum.threads.edit', $thread->id) }}" class="admin-btn admin-btn-sm admin-btn-primary">Editar</a>
                                    <form method="POST" action="{{ route('admin.forum.threads.destroy', $thread->id) }}" class="inline" onsubmit="return confirm('Mover este tópico para a lixeira?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Excluir</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center opacity-60">Nenhum tópico.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $threads->links() }}</div>
@endsection
