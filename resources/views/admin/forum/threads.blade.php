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
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int) $category === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
        </div>
    </form>

    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Título</th><th>Autor</th><th>Quadro</th><th>Respostas</th><th>Status</th><th>Criado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($threads as $thread)
                        <tr>
                            <td class="max-w-xs truncate">{{ $thread->title }}</td>
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
                            <td><a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}" target="_blank" rel="noopener" class="admin-btn admin-btn-sm admin-btn-secondary">Ver</a></td>
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
