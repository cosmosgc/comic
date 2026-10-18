@extends('layouts.admin-layout')

@section('title', 'Migrações')
@section('page-title', 'Migrações')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('migrate_output'))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Última saída do migrate</span>
            </div>
            <div class="admin-card-body">
                <pre class="admin-log">{{ session('migrate_output') }}</pre>
            </div>
        </div>
    @endif
    @if (session('cache_output'))
        <div class="admin-card mb-6">
            <div class="admin-card-header">
                <span>Última limpeza de cache</span>
            </div>
            <div class="admin-card-body">
                <pre class="admin-log">{{ session('cache_output') }}</pre>
            </div>
        </div>
    @endif

    {{-- Status overview --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="admin-stat">
            <div class="admin-stat-label">Conexão</div>
            <div class="mt-1 text-xl font-bold">{{ $report['connection'] }} <span class="text-sm font-medium text-zinc-500">({{ $report['driver'] }})</span></div>
            <div class="admin-stat-sub">{{ $report['database'] }}</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Arquivos de migração</div>
            <div class="admin-stat-value">{{ count($report['files']) }}</div>
            <div class="admin-stat-sub">{{ count($report['ran']) }} aplicadas · {{ count($report['pending']) }} pendentes</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Tabelas</div>
            <div class="admin-stat-value">{{ count($report['actualTables']) }}</div>
            <div class="admin-stat-sub">{{ count($report['expectedTables']) }} esperadas · {{ count($report['missingTables']) }} faltando · {{ count($report['extraTables']) }} extras</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Saúde</div>
            <div class="mt-2">
                @if ($report['healthy'])
                    <span class="admin-badge admin-badge-success">Em dia</span>
                @else
                    <span class="admin-badge admin-badge-danger">Precisa de atenção</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Problems callout --}}
    @if (!$report['healthy'])
        <div class="admin-alert admin-alert-warning mt-6">
            <strong>Algo está errado:</strong>
            <ul>
                @foreach ($report['pending'] as $name)
                    <li>Migração pendente: <code class="admin-code">{{ $name }}</code></li>
                @endforeach
                @foreach ($report['missingTables'] as $table)
                    <li>Tabela faltando: <code class="admin-code">{{ $table }}</code> (esperada por uma migração, não está no banco)</li>
                @endforeach
                @foreach ($report['ranWithoutFile'] as $name)
                    <li>Aplicada mas sem arquivo: <code class="admin-code">{{ $name }}</code> (o banco diz que rodou, mas não há arquivo — foi apagado?)</li>
                @endforeach
                @foreach ($report['tables'] as $table => $info)
                    @if (!empty($info['missingColumns']))
                        <li>Tabela <code class="admin-code">{{ $table }}</code> sem as colunas: <code class="admin-code">{{ implode(', ', $info['missingColumns']) }}</code></li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Run migrations (no console needed) --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Rodar migrações</span>
        </div>
        <div class="admin-card-body">
            @if (count($report['pending']) > 0)
                <p class="admin-muted mb-3">Roda <code class="admin-code">php artisan migrate --force</code> no servidor por este painel. Sem SSH.</p>
                <form method="POST" action="{{ route('admin.migrations.run') }}" onsubmit="return confirm('Rodar {{ count($report['pending']) }} migração(ões) pendente(s)?')">
                    @csrf
                    <input type="hidden" name="action" value="migrate">
                    <button type="submit" class="admin-btn admin-btn-primary">Rodar {{ count($report['pending']) }} migração(ões) pendente(s)</button>
                </form>
            @else
                <p class="admin-muted mb-0">Banco em dia — nada para rodar.</p>
            @endif
        </div>
    </div>

    {{-- Clear caches (no console needed) --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Limpar caches</span>
        </div>
        <div class="admin-card-body">
            <p class="admin-muted mb-3">
                Roda <code class="admin-code">view:clear</code>, <code class="admin-code">config:clear</code>,
                <code class="admin-code">route:clear</code>, <code class="admin-code">cache:clear</code> e
                <code class="admin-code">event:clear</code> no servidor por este painel. Sem SSH.
            </p>
            <p class="admin-muted mb-3">
                Use depois de um deploy que remova ou troque pacote: as views compiladas ficam no servidor (nunca são
                enviadas no deploy) e continuam chamando as classes antigas — o site responde 500 até serem limpas.
            </p>
            <p class="admin-muted mb-3">
                Não apaga os manifests de pacotes e serviços (<code class="admin-code">bootstrap/cache/packages.php</code>,
                <code class="admin-code">services.php</code>), que são mantidos como estão no servidor.
            </p>
            <form method="POST" action="{{ route('admin.caches.clear') }}" onsubmit="return confirm('Limpar os caches do servidor (views compiladas, config, rotas, cache e eventos)?')">
                @csrf
                <button type="submit" class="admin-btn admin-btn-primary">Limpar caches</button>
            </form>
        </div>
    </div>

    {{-- Pending migrations --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Migrações pendentes ({{ count($report['pending']) }})</span>
        </div>
        @if (count($report['pending']) === 0)
            <p class="admin-muted px-5 py-4">Nenhuma — todos os arquivos já rodaram.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Migração</th>
                            <th>Cria</th>
                            <th>Altera</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['files'] as $file)
                            @if (!$file['ran'])
                                <tr>
                                    <td><code>{{ $file['name'] }}</code></td>
                                    <td><code>{{ implode(', ', $file['tablesCreated']) ?: '—' }}</code></td>
                                    <td><code>{{ implode(', ', $file['tablesAltered']) ?: '—' }}</code></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Tables: expected vs actual --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Tabelas: migrações vs banco</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Tabela</th>
                        <th>Status</th>
                        <th>Linhas</th>
                        <th>Colunas (banco)</th>
                        <th>Colunas faltando</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['tables'] as $table => $info)
                        <tr>
                            <td><code>{{ $table }}</code></td>
                            <td>
                                @if (!$info['exists'])
                                    <span class="admin-badge admin-badge-danger">Falta no banco</span>
                                @elseif (!$info['expected'])
                                    <span class="admin-badge admin-badge-warning">Extra (sem migração)</span>
                                @else
                                    <span class="admin-badge admin-badge-success">OK</span>
                                @endif
                            </td>
                            <td>{{ $info['rows'] ?? '—' }}</td>
                            <td>{{ count($info['columns']) }}</td>
                            <td>
                                @if (!empty($info['missingColumns']))
                                    <code class="text-red-600 dark:text-red-400">{{ implode(', ', $info['missingColumns']) }}</code>
                                @else
                                    <span class="admin-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Per-table column detail --}}
    @foreach ($report['tables'] as $table => $info)
        @if ($info['exists'])
            <div class="admin-card mt-4">
                <div class="admin-card-header">
                    <span><code>{{ $table }}</code>
                    <span class="admin-muted">({{ count($info['columns']) }} colunas{{ $info['rows'] !== null ? ', '.$info['rows'].' linhas' : '' }})</span></span>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Coluna</th>
                                <th>Tipo</th>
                                <th>Permite nulo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($info['columns'] as $col)
                                <tr @if (in_array($col['name'], $info['missingColumns'])) class="bg-red-50 dark:bg-red-500/10" @endif>
                                    <td><code>{{ $col['name'] }}</code></td>
                                    <td><code>{{ $col['type'] }}</code></td>
                                    <td>{{ $col['nullable'] ? 'sim' : 'não' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

    {{-- All migration files --}}
    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Todos os arquivos ({{ count($report['files']) }})</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Migração</th>
                        <th>Status</th>
                        <th>Lote</th>
                        <th>Cria</th>
                        <th>Altera</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['files'] as $file)
                        <tr>
                            <td><code>{{ $file['name'] }}</code></td>
                            <td>
                                @if ($file['ran'])
                                    <span class="admin-badge admin-badge-success">Aplicada</span>
                                @else
                                    <span class="admin-badge admin-badge-danger">Pendente</span>
                                @endif
                            </td>
                            <td>{{ $file['batch'] ?? '—' }}</td>
                            <td><code>{{ implode(', ', $file['tablesCreated']) ?: '—' }}</code></td>
                            <td><code>{{ implode(', ', $file['tablesAltered']) ?: '—' }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($report['ranWithoutFile']) > 0)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm dark:border-zinc-800">
                <strong>Aplicadas no banco mas sem arquivo:</strong>
                <code class="admin-code">{{ implode(', ', $report['ranWithoutFile']) }}</code>
            </div>
        @endif
        @if (count($report['extraTables']) > 0)
            <div class="border-t border-zinc-200 px-5 py-3 text-sm dark:border-zinc-800">
                <strong>Tabelas extras no banco (sem migração):</strong>
                <code class="admin-code">{{ implode(', ', $report['extraTables']) }}</code>
            </div>
        @endif
    </div>
@endsection
