<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Comic Viewer')</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Custom Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="bg-dark text-light custom-root-container">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <a class="navbar-brand" href="{{ url('/') }}">Comic Viewer</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('comics.index') }}">Comics</a>
                </li>
                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('comics.create') }}">Upload</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            {{ Auth::user()->name }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="{{ route('profile.show') }}">Profile</a>
                            <div class="dropdown-divider"></div>
                            <form action="{{ route('logout') }}" method="POST" class="dropdown-item p-0">
                                @csrf
                                <button type="submit" class="btn btn-link btn-block text-left" style="text-decoration: none;">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('register') }}">Register</a>
                    </li>
                @endauth
                <!-- Add more navigation links if needed -->
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get the necessary data for analytics
            const analyticsData = {
                url: window.location.href,
                ip_address: '{{ request()->ip() }}',
                user_agent: navigator.userAgent,
                event_type: 'page_view',
                device_type: /Mobi|Android/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop',
                referral_source: document.referrer || 'Direct',
                campaign: '',
                duration: 0,
                browser: getBrowser(),
                os: getOS(),
                user_id: '{{ Auth::id() }}' // Pass the user ID from the server
            };

            // Function to get browser name
            function getBrowser() {
                if (navigator.userAgent.indexOf("Chrome") > -1) {
                    return "Chrome";
                } else if (navigator.userAgent.indexOf("Firefox") > -1) {
                    return "Firefox";
                } else if (navigator.userAgent.indexOf("Safari") > -1) {
                    return "Safari";
                } else if (navigator.userAgent.indexOf("Edge") > -1) {
                    return "Edge";
                } else {
                    return "Other";
                }
            }

            // Function to get OS name
            function getOS() {
                if (navigator.userAgent.indexOf("Win") > -1) {
                    return "Windows";
                } else if (navigator.userAgent.indexOf("Mac") > -1) {
                    return "MacOS";
                } else if (navigator.userAgent.indexOf("X11") > -1 || navigator.userAgent.indexOf("Linux") > -1) {
                    return "Linux";
                } else if (/Android/i.test(navigator.userAgent)) {
                    return "Android";
                } else if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                    return "iOS";
                } else {
                    return "Other";
                }
            }

            // Send the analytics data via API
            fetch('/api/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Include CSRF token for security
                },
                body: JSON.stringify(analyticsData),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                console.log('Analytics data stored:', data);
            })
            .catch(error => {
                console.error('Error storing analytics data:', error);
            });
        });

    </script>


    @yield('scripts')
</body>
</html>
