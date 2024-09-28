@extends('layouts.admin-layout')

@section('title', 'Admin Dashboard')

@section('page-title', 'Dashboard')

@section('content')
    <h1>Welcome, Admin</h1>
    <p>Total Users: {{ $totalUsers }}</p>
    <p>Total Comics: {{ $totalComics }}</p>

    <h2>Analytics</h2>
    <div class="row">
        <div class="col-md-12">
            <canvas id="analyticsChart"></canvas>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Prepare data for the combined chart

        const labels = [
            @foreach ($analyticsData as $data)
                '{{ $data->date }}',
            @endforeach
        ];

        // Page Views Data
        const pageViewsData = [
            @foreach ($analyticsData as $data)
                {{ $data->count }},
            @endforeach
        ];

        // Logins Data
        const loginsData = [
            @foreach ($loginsData as $data)
                {{ $data->count }},
            @endforeach
        ];

        const analyticsChart = new Chart(document.getElementById('analyticsChart'), {
            type: 'line',
            data: {
                labels: labels, // Shared labels for both datasets
                datasets: [
                    {
                        label: 'Page Views per Day',
                        data: pageViewsData,
                        fill: false,
                        borderColor: 'rgba(75, 192, 192, 1)', // Color for Page Views
                        tension: 0.1
                    },
                    {
                        label: 'Logins per Day',
                        data: loginsData,
                        fill: false,
                        borderColor: 'rgba(153, 102, 255, 1)', // Color for Logins
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
        });
    </script>
@endsection
