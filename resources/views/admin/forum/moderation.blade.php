@extends('layouts.admin-layout')

@section('title', 'Moderação do fórum')
@section('page-title', 'Moderação do fórum')

@section('content')
    <div class="admin-card mb-6">
        <div class="admin-card-header"><span>Denúncias</span></div>
        <div class="admin-card-body">
            <div class="flex flex-wrap gap-2">
                @foreach ($statuses as $key => $label)
                    <a href="{{ route('admin.forum.moderation', ['status' => $key]) }}"
                       class="admin-toggle {{ $status === $key ? 'admin-toggle-active' : '' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
                @endforeach
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Quando</th><th>Denunciante</th><th>Alvo</th><th>Motivo</th><th>Detalhes</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="whitespace-nowrap">{{ $report->created_at->diffForHumans() }}</td>
                            <td class="whitespace-nowrap">{{ $report->reporter?->name ?? '—' }}</td>
                            <td class="max-w-xs break-words">
                                @if ($report->post)
                                    <a href="{{ route('forum.thread', ['categorySlug' => $report->post->thread?->category?->slug, 'threadSlug' => $report->post->thread?->slug]) }}#post-{{ $report->post->id }}"
                                       target="_blank" rel="noopener" class="hover:underline">
                                        Post: {{ \Illuminate\Support\Str::limit($report->post->body, 70) }}
                                    </a>
                                @elseif ($report->thread)
                                    <a href="{{ route('forum.thread', ['categorySlug' => $report->thread->category?->slug, 'threadSlug' => $report->thread->slug]) }}"
                                       target="_blank" rel="noopener" class="hover:underline">
                                        Tópico: {{ \Illuminate\Support\Str::limit($report->thread->title, 60) }}
                                    </a>
                                @else
                                    <span class="opacity-50">removido</span>
                                @endif
                            </td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $report->reasonLabel() }}</span></td>
                            <td class="max-w-xs break-words">{{ $report->details ?: '—' }}</td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $report->status }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($report->status === 'pending')
                                    <form method="POST" action="{{ route('admin.forum.reports.resolve', $report) }}" class="inline">@csrf
                                        <input type="hidden" name="status" value="reviewed">
                                        <button class="admin-btn admin-btn-sm admin-btn-primary">Revisar</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.forum.reports.resolve', $report) }}" class="inline">@csrf
                                        <input type="hidden" name="status" value="dismissed">
                                        <button class="admin-btn admin-btn-sm admin-btn-secondary">Descartar</button>
                                    </form>
                                @else
                                    <span class="opacity-60">{{ $report->reviewed_at?->diffForHumans() ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center opacity-60">Nenhuma denúncia {{ $status === 'pending' ? 'pendente' : 'aqui' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><span>Posts aguardando aprovação</span></div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead><tr><th>Autor</th><th>Conteúdo</th><th>Tópico</th><th>Criado</th><th>Ações</th></tr></thead>
                <tbody>
                    @forelse ($pendingPosts as $post)
                        <tr>
                            <td class="whitespace-nowrap">{{ $post->author?->name ?? '—' }}</td>
                            <td class="max-w-md break-words">{{ \Illuminate\Support\Str::limit($post->body, 140) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($post->thread?->title, 50) }}</td>
                            <td class="whitespace-nowrap">{{ $post->created_at->diffForHumans() }}</td>
                            <td class="whitespace-nowrap">
                                <form method="POST" action="{{ route('forum.posts.approve', $post) }}" class="inline">@csrf
                                    <button class="admin-btn admin-btn-sm admin-btn-primary">Aprovar</button>
                                </form>
                                <form method="POST" action="{{ route('forum.posts.destroy', $post) }}" class="inline" onsubmit="return confirm('Remover?')">
                                    @csrf @method('DELETE')
                                    <button class="admin-btn admin-btn-sm admin-btn-danger">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center opacity-60">Nada pendente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (method_exists($reports, 'links'))
        <div class="mt-4">{{ $reports->links() }}</div>
    @endif
@endsection
