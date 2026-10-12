@extends('layouts.admin-layout')

@section('title', 'Análises')

@section('page-title', 'Análises')

@section('content')
    <div class="admin-card">
        <div class="admin-card-header">
            <span>Atividades recentes</span>
        </div>
        <div class="admin-table-wrap">
            <table id="analyticsTable" class="admin-table admin-table-compact">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>IP</th>
                        <th>URL</th>
                        <th>Evento</th>
                        <th>Agente</th>
                        <th>Dispositivo</th>
                        <th>Navegador</th>
                        <th>SO</th>
                        <th>Dur. (s)</th>
                        <th>Origem</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($analytics as $entry)
                        <tr>
                            <td class="whitespace-nowrap">{{ optional($entry->user)->name ?? 'Visitante' }}</td>
                            <td class="whitespace-nowrap"><code>{{ $entry->ip_address }}</code></td>
                            <td class="max-w-64 break-all" title="{{ $entry->url }}">{{ $entry->url }}</td>
                            <td><span class="admin-badge admin-badge-secondary">{{ $entry->event_type }}</span></td>
                            <td class="max-w-48 truncate" title="{{ $entry->user_agent }}">{{ $entry->user_agent }}</td>
                            <td class="whitespace-nowrap">{{ $entry->device_type }}</td>
                            <td class="whitespace-nowrap">{{ $entry->browser }}</td>
                            <td class="whitespace-nowrap">{{ $entry->os }}</td>
                            <td>{{ $entry->duration }}</td>
                            <td class="max-w-40 truncate" title="{{ $entry->referral_source }}">{{ $entry->referral_source }}</td>
                            <td class="whitespace-nowrap">{{ $entry->created_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $analytics->links() }}
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('#analyticsTable').DataTable({
                order: [[10, 'desc']], // Sort by the 11th column (created_at), descending
                autoWidth: false
            });
        });
    </script>
@endsection
