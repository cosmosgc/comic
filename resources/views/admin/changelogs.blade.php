@extends('layouts.admin-layout')

@section('title', 'Gerenciar changelog')
@section('page-title', 'Changelog')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif

    <p class="admin-muted mb-6">
        Um arquivo por PR em <code class="admin-code">changelogs/*.json</code>, exibido em
        <a href="{{ route('changelog.index') }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400">/changelog</a>.
    </p>

    {{-- PRs missing an entry --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>PRs sem entrada no changelog ({{ $repo }})</span>
        </div>
        @if ($fetchError)
            <p class="px-5 py-4 text-sm text-red-700 dark:text-red-300">{{ $fetchError }}</p>
        @elseif (count($missing) === 0)
            <p class="admin-muted px-5 py-4">Tudo documentado — nenhum PR recente faltando.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PR</th>
                            <th>Título</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($missing as $pr)
                            <tr>
                                <td>
                                    <a href="{{ $pr['url'] }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline dark:text-indigo-400">#{{ $pr['number'] }}</a>
                                </td>
                                <td>
                                    {{ $pr['title'] }}
                                    <br><span class="admin-muted text-xs">por {{ $pr['author'] }} · {{ $pr['created_at'] }}</span>
                                </td>
                                <td>
                                    @if ($pr['merged'])
                                        <span class="admin-badge admin-badge-success">merged</span>
                                    @else
                                        <span class="admin-badge admin-badge-info">{{ $pr['state'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.changelogs.import') }}" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="number" value="{{ $pr['number'] }}">
                                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Importar e editar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Existing entries --}}
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Entradas ({{ count($entries) }})</span>
            <a href="{{ route('admin.changelogs.create') }}" class="admin-btn admin-btn-success admin-btn-sm">Nova entrada</a>
        </div>
        @if (count($entries) === 0)
            <p class="admin-muted px-5 py-4">Sem entradas ainda.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>PR</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td><code>{{ $entry['date'] }}</code></td>
                                <td>
                                    <a href="{{ route('changelog.show', $entry['id']) }}" target="_blank" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $entry['title'] }}</a>
                                </td>
                                <td><span class="admin-badge admin-badge-secondary">{{ $entry['category'] }}</span></td>
                                <td>{{ $entry['pr'] ? '#'.$entry['pr'] : '—' }}</td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.changelogs.edit', $entry['id']) }}" class="admin-btn admin-btn-warning admin-btn-sm">Editar</a>
                                    <form method="POST" action="{{ route('admin.changelogs.destroy', $entry['id']) }}"
                                          class="inline-block" onsubmit="return confirm('Excluir esta entrada?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
