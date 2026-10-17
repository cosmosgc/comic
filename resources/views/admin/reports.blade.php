@extends('layouts.admin-layout')

@section('title', 'Denúncias')

@section('page-title', 'Denúncias')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Fila de moderação</span>
        </div>
        <div class="admin-card-body space-y-4">
            <div>
                <span class="admin-label">Status</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($statuses as $key => $label)
                        <a href="{{ route('admin.reports', ['status' => $key]) }}"
                           class="admin-toggle {{ $status === $key ? 'admin-toggle-active' : '' }}">
                            {{ $label }} ({{ $counts[$key] ?? 0 }})
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Denunciante</th>
                        <th>Post</th>
                        <th>Autor do post</th>
                        <th>Motivo</th>
                        <th>Detalhes</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="whitespace-nowrap" title="{{ $report->created_at }}">{{ $report->created_at?->diffForHumans() }}</td>
                            <td class="whitespace-nowrap">{{ $report->reporter?->name ?? 'Usuário removido' }}</td>
                            <td class="max-w-xs break-words" title="{{ $report->post?->text }}">
                                @if ($report->post)
                                    <a href="{{ route('posts.show', $report->post) }}" target="_blank" rel="noopener" class="hover:underline">
                                        {{ \Illuminate\Support\Str::limit($report->post->text, 80) ?: '#'.$report->post_id }}
                                    </a>
                                @else
                                    <span class="opacity-50">post removido</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $report->post?->author?->name ?? '—' }}</td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $report->reasonLabel() }}</span></td>
                            <td class="max-w-xs break-words" title="{{ $report->details }}">{{ $report->details ?: '—' }}</td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $report->status }}</span></td>
                            <td class="whitespace-nowrap">
                                @if ($report->status === 'pending')
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="reviewed">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">Revisar</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="dismissed">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Descartar</button>
                                    </form>
                                @else
                                    <span class="opacity-60">{{ $report->reviewed_at?->diffForHumans() ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center opacity-60">Nenhuma denúncia {{ $status === 'pending' ? 'pendente' : 'aqui' }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $reports->links() }}
    </div>
@endsection
