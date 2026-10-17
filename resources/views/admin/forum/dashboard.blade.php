@extends('layouts.admin-layout')

@section('title', 'Fórum')
@section('page-title', 'Fórum')

@section('content')
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            'Categorias' => $totals['categories'],
            'Tópicos' => $totals['threads'],
            'Posts' => $totals['posts'],
            'Membros' => $totals['members'],
            'Denúncias pendentes' => $totals['pending_reports'],
            'Posts pendentes' => $totals['pending_posts'],
        ] as $label => $value)
            <div class="admin-card">
                <div class="admin-card-body">
                    <p class="text-2xl font-bold">{{ number_format($value) }}</p>
                    <p class="text-xs opacity-70">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 admin-card">
        <div class="admin-card-header">
            <span>Atividade (30 dias)</span>
        </div>
        <div class="admin-card-body">
            <canvas id="forumChart" height="90"></canvas>
        </div>
    </div>

    <div class="mt-6 admin-card">
        <div class="admin-card-header">
            <span>Últimos tópicos</span>
            <a href="{{ route('admin.forum.threads') }}" class="text-xs underline">ver todos</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead>
                    <tr><th>Título</th><th>Autor</th><th>Quadro</th><th>Quando</th></tr>
                </thead>
                <tbody>
                    @forelse ($latestThreads as $thread)
                        <tr>
                            <td>
                                <a href="{{ route('forum.thread', ['categorySlug' => $thread->category?->slug, 'threadSlug' => $thread->slug]) }}"
                                   target="_blank" rel="noopener" class="hover:underline">{{ $thread->title }}</a>
                            </td>
                            <td>{{ $thread->author?->name ?? '—' }}</td>
                            <td>{{ $thread->category?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $thread->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center opacity-60">Sem tópicos ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.forum.categories') }}" class="admin-btn admin-btn-primary">Categorias</a>
        <a href="{{ route('admin.forum.threads') }}" class="admin-btn admin-btn-secondary">Tópicos</a>
        <a href="{{ route('admin.forum.moderation') }}" class="admin-btn admin-btn-secondary">Moderação</a>
        <a href="{{ route('admin.forum.moderators') }}" class="admin-btn admin-btn-secondary">Moderadores</a>
        <a href="{{ route('admin.forum.bans') }}" class="admin-btn admin-btn-secondary">Banimentos</a>
        <a href="{{ route('admin.forum.settings') }}" class="admin-btn admin-btn-secondary">Configurações</a>
    </div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const labels = @json(array_keys($activity));
        const threads = @json(array_map(fn ($row) => $row['threads'], array_values($activity)));
        const posts = @json(array_map(fn ($row) => $row['posts'], array_values($activity)));
        new Chart(document.getElementById('forumChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Tópicos', data: threads, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.15)', tension: .3, fill: true },
                    { label: 'Posts', data: posts, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)', tension: .3, fill: true },
                ],
            },
            options: { responsive: true, plugins: { legend: { labels: { color: '#a1a1aa' } } }, scales: { x: { ticks: { color: '#71717a' } }, y: { ticks: { color: '#71717a' }, beginAtZero: true } } },
        });
    });
</script>
@endsection
