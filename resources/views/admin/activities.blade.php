@extends('layouts.admin-layout')

@section('title', 'Atividades recentes')

@section('page-title', 'Atividades recentes')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Interações dos usuários</span>
            <a href="{{ route('admin.activities.report') }}" class="admin-btn admin-btn-secondary admin-btn-sm">Relatório →</a>
        </div>
        <div class="admin-card-body space-y-4">
            <div>
                <span class="admin-label">Tipo de atividade</span>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.activities', array_filter(['q' => $search ?: null])) }}"
                       class="admin-toggle {{ $activeAction === null ? 'admin-toggle-active' : '' }}">
                        Todas
                    </a>
                    @foreach ($actions as $key => $label)
                        <a href="{{ route('admin.activities', array_filter(['action' => $key, 'q' => $search ?: null])) }}"
                           class="admin-toggle {{ $activeAction === $key ? 'admin-toggle-active' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @if ($activeAction)
                    <input type="hidden" name="action" value="{{ $activeAction }}">
                @endif
                <div class="min-w-52 flex-1">
                    <label for="q" class="admin-label">Buscar (usuário, texto, URL, IP)</label>
                    <input type="text" name="q" id="q" class="admin-input" value="{{ $search }}" placeholder="ex. naruto, login, 127.0.0.1">
                </div>
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Filtrar</button>
                @if ($activeAction || $search !== '')
                    <a href="{{ route('admin.activities') }}" class="admin-btn admin-btn-sm admin-btn-secondary">Limpar</a>
                @endif
            </form>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>O quê</th>
                        <th>Link</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td class="whitespace-nowrap" title="{{ $activity->created_at }}">{{ $activity->created_at?->diffForHumans() }}</td>
                            @php($knownUser = $activity->user ?? ($activity->ip_address ? $ipUsers->get($activity->ip_address) : null))
                            <td class="whitespace-nowrap">
                                @if ($activity->user)
                                    {{ $activity->user->name }}
                                @elseif ($knownUser)
                                    {{ $knownUser->name }}
                                    <span class="admin-badge admin-badge-secondary" title="Atribuído pelo IP {{ $activity->ip_address }}">via IP</span>
                                @else
                                    Visitante
                                @endif
                            </td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $activity->label() }}</span></td>
                            <td class="max-w-md break-words" title="{{ $activity->description }}">{{ $activity->description ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                @if ($activity->url)
                                    <a href="{{ $activity->url }}" target="_blank" rel="noopener" class="admin-btn admin-btn-sm">Ver</a>
                                @else
                                    <span class="opacity-50">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap"><code>{{ $activity->ip_address ?? '—' }}</code></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center opacity-60">Nenhuma atividade registrada ainda. Interaja com o site (busque, curta, poste) e volte aqui.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $activities->links() }}
    </div>
@endsection
