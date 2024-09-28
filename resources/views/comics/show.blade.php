<!DOCTYPE html>
<html>
<head>
    <title>{{ $comic->title }} - Comic Reader</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #1a1a1a;
        }

        .comic-container {
            position: relative;
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        .comic-image {
            max-height: 100vh;
            max-width: 100%;
            object-fit: contain;
            transform-origin: center center;
        }

        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: none;
            cursor: pointer;
        }

        .arrow-left {
            left: 10px;
        }

        .arrow-right {
            right: 10px;
        }

        .arrow:hover {
            background-color: rgba(255, 255, 255, 0.8);
        }
    </style>

    <!-- Dynamic Meta Tags for Sharing -->
    <meta property="og:title" content="{{ $comic->title }}" />
    <meta property="og:description" content="Read the comic titled '{{ $comic->title }}'." />
    <meta property="og:image" content="{{ asset('storage/' . $comic->image_path) }}" />
    <meta property="og:image:width" content="630">
    <meta property="og:image:height" content="1200">

    <meta property="twitter:title" content="{{ $comic->title }}" />
    <meta property="twitter:description" content="Read the comic titled '{{ $comic->title }}'." />
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:image:src" content="{{ asset('storage/' . $comic->image_path) }}" />
</head>
<body>
    <div class="comic-container">
        @foreach ($comic->pages as $page)
            <img class="comic-image" src="{{ asset('storage/' . $page->image_path) }}" alt="Page {{ $page->page_number }}" style="display: none;">
        @endforeach

        <div class="arrow arrow-left"></div>
        <div class="arrow arrow-right"></div>
    </div>

    <script>
        var currentPage = 0;
        var currentZoom = 1;
        var zoomIncrement = 0.1;
        var minZoom = 0.5;
        var maxZoom = 2;

        var comicPages = document.getElementsByClassName("comic-image");
        comicPages[0].style.display = "block"; // Show first page initially
        var arrowLeft = document.querySelector(".arrow-left");
        var arrowRight = document.querySelector(".arrow-right");

        arrowLeft.addEventListener("click", showPreviousPage);
        arrowRight.addEventListener("click", showNextPage);

        document.addEventListener("keydown", function (event) {
            switch (event.key) {
                case "ArrowLeft":
                case "a":
                    showPreviousPage();
                    break;
                case "ArrowRight":
                case "d":
                    showNextPage();
                    break;
                case "z": // Zoom in
                    currentZoom = Math.min(currentZoom + zoomIncrement, maxZoom);
                    updateZoom();
                    break;
                case "x": // Zoom out
                    currentZoom = Math.max(currentZoom - zoomIncrement, minZoom);
                    updateZoom();
                    break;
            }
        });
        var touchstartX = 0;
        var touchendX = 0;
        var touchZoomDistance = 0;

        document.addEventListener("touchstart", function (event) {
            touchstartX = event.changedTouches[0].screenX;
        });

        document.addEventListener("touchend", function (event) {
            touchendX = event.changedTouches[0].screenX;
            handleSwipe();
        });

        function updateZoom() {
            comicPages[currentPage].style.transform = `scale(${currentZoom})`;
        }
        function handleSwipe() {
            if (touchendX < touchstartX) {
                showNextPage();
            } else if (touchendX > touchstartX) {
                showPreviousPage();
            }
        }

        function showPreviousPage() {
            if (currentPage > 0) {
                comicPages[currentPage].style.display = "none";
                currentPage--;
                comicPages[currentPage].style.display = "block";
            }
        }

        function showNextPage() {
            if (currentPage < comicPages.length - 1) {
                comicPages[currentPage].style.display = "none";
                currentPage++;
                comicPages[currentPage].style.display = "block";
            }
        }

        document.querySelector(".comic-container").addEventListener("mousemove", function (event) {
            var containerWidth = this.offsetWidth;
            var mouseX = event.clientX;

            if (mouseX < containerWidth / 2) {
                arrowLeft.style.display = "block";
                arrowRight.style.display = "none";
            } else {
                arrowLeft.style.display = "none";
                arrowRight.style.display = "block";
            }
        });

        document.querySelector(".comic-container").addEventListener("mouseleave", function () {
            arrowLeft.style.display = "none";
            arrowRight.style.display = "none";
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get the necessary data for analytics
            const analyticsData = {
                url: window.location.href,
                ip_address: '{{ request()->ip() }}',
                user_agent: navigator.userAgent,
                event_type: 'page_view' // You can change this for different types of events
            };

            // Send the analytics data via API
            fetch('/api/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Include CSRF token for security
                },
                body: JSON.stringify(analyticsData)
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
</body>
</html>
