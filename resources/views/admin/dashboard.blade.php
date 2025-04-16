@extends('layouts.admin-layout')

@section('title', 'Admin Dashboard')

@section('page-title', 'Dashboard')

@section('content')
    <h1>Welcome, Admin</h1>
    <p>Total Users: {{ $totalUsers }}</p>
    <p>Total Comics: {{ $totalComics }}</p>

    <h2>Analytics</h2>
    <form method="GET" class="mb-4">
        <label for="start_date">Start Date:</label>
        <input type="date" name="start_date" value="{{ request('start_date') }}">

        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" value="{{ request('end_date') }}">

        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    </form>

    <div class="mb-3">
        <label for="timeScale" class="form-label">Select Time Range:</label>
        <select id="timeScale" class="form-select" style="max-width: 200px;">
            <option value="daily" selected>Daily</option>
            <option value="monthly">Monthly</option>
            <option value="annual">Annually</option>
        </select>
    </div>

    <div class="row">
        <div class="col-md-12">
            <canvas id="analyticsChart"></canvas>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
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

        const ctx = document.getElementById('analyticsChart').getContext('2d');
        let analyticsChart = new Chart(ctx, getChartConfig('daily'));

        document.getElementById('timeScale').addEventListener('change', function () {
            const selectedScale = this.value;
            analyticsChart.destroy(); // destroy old chart
            analyticsChart = new Chart(ctx, getChartConfig(selectedScale)); // re-init chart
        });

        function getChartConfig(scale) {
            return {
                type: 'line',
                data: {
                    labels: rawData[scale].labels,
                    datasets: [
                        {
                            label: 'Page Views',
                            data: rawData[scale].pageViews,
                            fill: false,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    responsive: true
                }
            };
        }
    </script>
@endsection
