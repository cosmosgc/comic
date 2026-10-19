@extends('layouts.admin-layout')

@section('title', 'Painel Admin')

@section('page-title', 'Painel')

@section('content')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="admin-stat">
            <div class="admin-stat-label">Usuários</div>
            <div class="admin-stat-value">{{ $totalUsers }}</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-label">Comics</div>
            <div class="admin-stat-value">{{ $totalComics }}</div>
        </div>
    </div>

    <div class="admin-card mt-6">
        <div class="admin-card-header">
            <span>Análises</span>
        </div>
        <div class="admin-card-body space-y-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="start_date" class="admin-label">Data inicial</label>
                    <input type="date" name="start_date" id="start_date" class="admin-input" value="{{ request('start_date') }}">
                </div>
                <div>
                    <label for="end_date" class="admin-label">Data final</label>
                    <input type="date" name="end_date" id="end_date" class="admin-input" value="{{ request('end_date') }}">
                </div>
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Aplicar</button>
            </form>

            <div class="max-w-xs">
                <label for="timeScale" class="admin-label">Período</label>
                <select id="timeScale" class="admin-input">
                    <option value="daily" selected>Diário</option>
                    <option value="monthly">Mensal</option>
                    <option value="annual">Anual</option>
                </select>
            </div>

            <div class="relative h-80">
                <canvas id="analyticsChart"></canvas>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        // Deferred module: Chart.js comes from resources/js/admin.js.
        const rawData = {
            daily: {
                labels: [
                    @foreach ($analyticsData['daily'] as $data)
                        '{{ $data->date }}',
                    @endforeach
                ],
                pageViews: [
                    @foreach ($analyticsData['daily'] as $data)
                        {{ $data->count }},
                    @endforeach
                ]
            },
            monthly: {
                labels: [
                    @foreach ($analyticsData['monthly'] as $data)
                        '{{ $data->date }}',
                    @endforeach
                ],
                pageViews: [
                    @foreach ($analyticsData['monthly'] as $data)
                        {{ $data->count }},
                    @endforeach
                ]
            },
            annual: {
                labels: [
                    @foreach ($analyticsData['annual'] as $data)
                        '{{ $data->date }}',
                    @endforeach
                ],
                pageViews: [
                    @foreach ($analyticsData['annual'] as $data)
                        {{ $data->count }},
                    @endforeach
                ]
            }
        };

        function adminIsDark() {
            return document.documentElement.classList.contains('dark');
        }

        function getChartConfig(scale) {
            const dark = adminIsDark();
            const tick = dark ? '#a1a1aa' : '#52525b';
            const grid = dark ? '#27272a' : '#e4e4e7';
            return {
                type: 'line',
                data: {
                    labels: rawData[scale].labels,
                    datasets: [
                        {
                            label: 'Visualizações',
                            data: rawData[scale].pageViews,
                            fill: false,
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.15)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: tick },
                            grid: { color: grid }
                        },
                        x: {
                            ticks: { color: tick },
                            grid: { color: grid }
                        }
                    },
                    plugins: {
                        legend: { labels: { color: tick } }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            };
        }

        const ctx = document.getElementById('analyticsChart').getContext('2d');
        let analyticsChart = new Chart(ctx, getChartConfig('daily'));

        function rebuildChart() {
            const selectedScale = document.getElementById('timeScale').value;
            analyticsChart.destroy();
            analyticsChart = new Chart(ctx, getChartConfig(selectedScale));
        }

        document.getElementById('timeScale').addEventListener('change', rebuildChart);
        document.addEventListener('admin-theme-changed', rebuildChart);
    </script>
@endpush
