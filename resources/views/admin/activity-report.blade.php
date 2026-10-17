@extends('layouts.admin-layout')

@section('title', 'Relatório de atividades')

@section('page-title', 'Relatório de atividades')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="start_date" class="admin-label">Data inicial</label>
                <input type="date" name="start_date" id="start_date" class="admin-input" value="{{ $startDate }}">
            </div>
            <div>
                <label for="end_date" class="admin-label">Data final</label>
                <input type="date" name="end_date" id="end_date" class="admin-input" value="{{ $endDate }}">
            </div>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Aplicar</button>
        </form>
        <a href="{{ route('admin.activities') }}" class="admin-btn admin-btn-secondary admin-btn-sm">← Ver lista</a>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="admin-stat">
            <div class="admin-stat-label">Atividades</div>
            <div class="admin-stat-value">{{ number_format($total) }}</div>
            @if ($trend !== null)
                <div class="mt-1 text-xs font-semibold {{ $trend >= 0 ? 'text-emerald-500' : 'text-red-500' }}">
                    {{ $trend >= 0 ? '▲' : '▼' }} {{ abs($trend) }}% vs. período anterior
                </div>
            @endif
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Usuários únicos</div>
            <div class="admin-stat-value">{{ number_format($uniqueUsers) }}</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Visitantes</div>
            <div class="admin-stat-value">{{ number_format($guestCount) }}</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Logins</div>
            <div class="admin-stat-value">{{ number_format($loginCount) }}</div>
        </div>
    </div>

    <div class="admin-card mt-6">
        <div class="admin-card-header"><span>Atividade por dia</span></div>
        <div class="admin-card-body">
            <div class="relative h-72">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="admin-card">
            <div class="admin-card-header"><span>Distribuição por tipo</span></div>
            <div class="admin-card-body">
                <div class="relative h-80">
                    <canvas id="actionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span>Usuários mais ativos</span></div>
            <div class="admin-card-body">
                @if ($topUsers->isEmpty())
                    <p class="text-sm opacity-60">Sem dados no período.</p>
                @else
                    <div class="relative h-80">
                        <canvas id="usersChart"></canvas>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="admin-card">
            <div class="admin-card-header"><span>🔎 Buscas mais populares</span></div>
            <div class="admin-card-body">
                @forelse ($popularSearches as $term => $count)
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-800">
                        <span class="truncate">{{ $loop->iteration }}. {{ $term }}</span>
                        <span class="admin-badge admin-badge-primary">{{ $count }}×</span>
                    </div>
                @empty
                    <p class="text-sm opacity-60">Nenhuma busca registrada no período.</p>
                @endforelse
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><span>❤️ Comics mais curtidos</span></div>
            <div class="admin-card-body">
                @forelse ($topLiked as $row)
                    @php($comic = $likedComics->get($row->subject_id))
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 py-2 last:border-0 dark:border-zinc-800">
                        <span class="truncate">
                            {{ $loop->iteration }}.
                            @if ($comic)
                                <a href="{{ route('comics.showBySlug', $comic->slug) }}" target="_blank" rel="noopener" class="hover:underline">{{ $comic->title }}</a>
                            @else
                                <span class="opacity-60">Comic #{{ $row->subject_id }} (removido)</span>
                            @endif
                        </span>
                        <span class="admin-badge admin-badge-danger">{{ $row->count }} ❤</span>
                    </div>
                @empty
                    <p class="text-sm opacity-60">Nenhuma curtida registrada no período.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="admin-card mt-6">
        <div class="admin-card-header"><span>Horário de pico</span></div>
        <div class="admin-card-body">
            <div class="relative h-64">
                <canvas id="hourChart"></canvas>
            </div>
            <p class="admin-help">Baseado nas últimas {{ number_format(array_sum($byHour)) }} atividades do período.</p>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const reportData = {
            daily: {
                labels: @json($dailyLabels),
                counts: @json($dailyCounts),
            },
            actions: {
                labels: @json($actionLabels),
                counts: @json($actionCounts),
            },
            users: {
                labels: @json($topUsers->map(fn ($u) => optional($u->user)->name ?? 'Usuário #'.$u->user_id)->all()),
                counts: @json($topUsers->pluck('count')->map(fn ($c) => (int) $c)->all()),
            },
            hours: {
                labels: Array.from({ length: 24 }, (_, i) => i + 'h'),
                counts: @json(array_values($byHour)),
            },
        };

        function adminIsDark() {
            return document.documentElement.classList.contains('dark');
        }

        function palette() {
            const dark = adminIsDark();
            return {
                tick: dark ? '#a1a1aa' : '#52525b',
                grid: dark ? '#27272a' : '#e4e4e7',
            };
        }

        const charts = [];

        function buildCharts() {
            const { tick, grid } = palette();
            const baseScales = {
                y: { beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } },
                x: { ticks: { color: tick }, grid: { color: grid } },
            };

            charts.push(new Chart(document.getElementById('dailyChart'), {
                type: 'line',
                data: {
                    labels: reportData.daily.labels,
                    datasets: [{
                        label: 'Atividades',
                        data: reportData.daily.counts,
                        fill: true,
                        borderColor: 'rgba(99, 102, 241, 1)',
                        backgroundColor: 'rgba(99, 102, 241, 0.15)',
                        tension: 0.25,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: tick } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } },
                        x: { ticks: { color: tick, maxRotation: 0, autoSkip: true }, grid: { color: grid } },
                    },
                },
            }));

            charts.push(new Chart(document.getElementById('actionChart'), {
                type: 'doughnut',
                data: {
                    labels: reportData.actions.labels,
                    datasets: [{
                        data: reportData.actions.counts,
                        backgroundColor: [
                            '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#0ea5e9',
                            '#a855f7', '#ec4899', '#14b8a6', '#f97316', '#84cc16',
                            '#8b5cf6', '#06b6d4', '#eab308', '#f43f5e', '#22c55e',
                            '#3b82f6', '#d946ef',
                        ],
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { color: tick, boxWidth: 12 } } },
                },
            }));

            const usersCanvas = document.getElementById('usersChart');
            if (usersCanvas) {
                charts.push(new Chart(usersCanvas, {
                    type: 'bar',
                    data: {
                        labels: reportData.users.labels,
                        datasets: [{ label: 'Ações', data: reportData.users.counts, backgroundColor: 'rgba(16, 185, 129, 0.75)' }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid } },
                            y: { ticks: { color: tick }, grid: { color: grid } },
                        },
                    },
                }));
            }

            charts.push(new Chart(document.getElementById('hourChart'), {
                type: 'bar',
                data: {
                    labels: reportData.hours.labels,
                    datasets: [{ label: 'Atividades', data: reportData.hours.counts, backgroundColor: 'rgba(245, 158, 11, 0.75)' }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: baseScales,
                },
            }));
        }

        function rebuildCharts() {
            charts.forEach((c) => c.destroy());
            charts.length = 0;
            buildCharts();
        }

        buildCharts();
        document.addEventListener('admin-theme-changed', rebuildCharts);
    </script>
@endsection
