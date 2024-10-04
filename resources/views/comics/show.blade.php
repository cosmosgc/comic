<!DOCTYPE html>
<html>
<head>
    <title>{{ $comic->title }} - Comic Reader</title>
    <meta property="og:title" content="{{$comic->title}}" />
    <meta property="og:description" content="{{$comic->author}}" />
    <meta property="og:image" content="{{ asset('storage/' . $comic->image_path) }}" />
    <meta property="og:image:width" content="630">
    <meta property="og:image:height" content="1200">

    <meta property="twitter:title" content="{{$comic->title}}" />
    <meta property="twitter:description" content="{{$comic->author}}" />
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:image:src" content="{{ asset('storage/' . $comic->image_path) }}" />

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

        .pagination {
            display: flex;
            justify-content: center;
            padding: 10px;
            background-color: #333;
        }

        .pagination a {
            margin: 0 5px;
            padding: 5px 10px;
            background-color: #fff;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
        }

        .pagination a.active {
            background-color: #007bff;
            color: #fff;
        }

        .pagination a:hover {
            background-color: #555;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="comic-container">
        @foreach ($comic->pages as $page)
            <img class="comic-image" src="{{ asset('storage/' . $page->image_path) }}" alt="Page {{ $page->page_number }}" style="display: none;">
        @endforeach

        <div class="arrow arrow-left"></div>
        <div class="arrow arrow-right"></div>
    </div>

    <!-- Pagination at the bottom -->
    <div class="pagination">

        <button class="pagination-btn" id="root-page">Voltar</button>
        <button class="pagination-btn" id="first-page" style="
    display: none;
">First</button>

        <span id="pagination-links"></span>

        <button class="pagination-btn" id="last-page" style="
    display: none;
">Last</button>
    </div>

    <script>
        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        // Function to get a cookie
        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Function to erase a cookie
        function eraseCookie(name) {
            document.cookie = name + '=; Max-Age=-99999999;';
        }

        var currentPage = 0;
        var currentZoom = 1;
        var zoomIncrement = 0.1;
        var minZoom = 0.5;
        var maxZoom = 2;

        var comicPages = document.getElementsByClassName("comic-image");
        var comicId = '{{ $comic->id }}'; // Get comic ID from the backend

        // Retrieve stored page from cookie, or start at the first page if no cookie exists
        var storedPage = getCookie("comic_" + comicId + "_page");
        if (storedPage !== null && !isNaN(storedPage) && storedPage != '') {
            currentPage = parseInt(storedPage);
            console.log(comicPages, currentPage, storedPage);
            comicPages[1].style.display = "none";
            comicPages[currentPage].style.display = "block";
        }

        comicPages[currentPage].style.display = "block"; // Show first page initially
        var arrowLeft = document.querySelector(".arrow-left");
        var arrowRight = document.querySelector(".arrow-right");

        arrowLeft.addEventListener("click", showPreviousPage);
        arrowRight.addEventListener("click", showNextPage);

        document.addEventListener("keydown", function (event) {
            console.log(event.key);
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
                case "Escape":
                    exitToRoot();
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
        var totalPages = comicPages.length;

        function renderPagination() {
            var paginationLinks = document.getElementById("pagination-links");
            paginationLinks.innerHTML = ""; // Clear the pagination links

            var startPage = Math.max(0, currentPage - 3);
            var endPage = Math.min(totalPages - 1, currentPage + 3);

            // Add "First" button if not on the first page
            if (currentPage > 0) {
                var firstButton = document.createElement("button");
                firstButton.innerHTML = "Primeiro";
                firstButton.classList.add("pagination-btn");
                firstButton.addEventListener("click", function() {
                    goToPage(0); // Go to the first page
                });
                paginationLinks.appendChild(firstButton);
            }

            // Add page links
            for (var i = startPage; i <= endPage; i++) {
                var pageButton = document.createElement("button");
                pageButton.innerHTML = i + 1; // Pages are 1-based in UI
                pageButton.classList.add("pagination-btn");

                if (i === currentPage) {
                    pageButton.classList.add("active");
                }

                pageButton.addEventListener("click", (function(page) {
                    return function() {
                        goToPage(page); // Go to the clicked page
                    };
                })(i));

                paginationLinks.appendChild(pageButton);
            }

            // Add "Last" button if not on the last page
            if (currentPage < totalPages - 1) {
                var lastButton = document.createElement("button");
                lastButton.innerHTML = "Ultimo";
                lastButton.classList.add("pagination-btn");
                lastButton.addEventListener("click", function() {
                    goToPage(totalPages - 1); // Go to the last page
                });
                paginationLinks.appendChild(lastButton);
            }
        }

        // Go to a specific page
        function goToPage(page) {
            if (page >= 0 && page < totalPages) {
                comicPages[currentPage].style.display = "none";
                currentPage = page;
                comicPages[currentPage].style.display = "block";
                setCookie("comic_" + comicId + "_page", currentPage, 30);
                renderPagination(); // Re-render pagination links
            }
        }
        function exitToRoot(){
            window.location.href = '/';
        }

        // Root button event (Go back to root)
        document.getElementById("root-page").addEventListener("click", function() {
            window.location.href = '/'; // Replace with the actual root URL
        });


        // First button event
        document.getElementById("first-page").addEventListener("click", function() {
            goToPage(0);
        });

        // Last button event
        document.getElementById("last-page").addEventListener("click", function() {
            goToPage(totalPages - 1);
        });

        // Initial rendering of pagination when page is loaded
        renderPagination();


        function setCurrentPage(page) {
            comicPages[currentPage].style.display = "none";
            currentPage = page;
            comicPages[currentPage].style.display = "block";
            setCookie("comic_" + comicId + "_page", currentPage, 30);

            // Update pagination active class
            updatePaginationActiveClass();
        }


        function showPreviousPage() {
            if (currentPage > 0) {
                comicPages[currentPage].style.display = "none";
                currentPage--;
                comicPages[currentPage].style.display = "block";
                setCookie("comic_" + comicId + "_page", currentPage, 30);
            }
        }

        function showNextPage() {
            if (currentPage < comicPages.length - 1) {
                comicPages[currentPage].style.display = "none";
                currentPage++;
                comicPages[currentPage].style.display = "block";
                setCookie("comic_" + comicId + "_page", currentPage, 30);
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
                event_type: 'page_view', // You can change this for different types of events
                device_type: /Mobi|Android/i.test(navigator.userAgent) ? 'Mobile' : 'Desktop', // Determine device type
                referral_source: document.referrer || 'Direct', // Capture the referral source
                campaign: '', // You can set this dynamically if needed
                duration: 0, // Initialize duration, set it later if needed
                browser: getBrowser(), // Call a function to get the browser name
                os: getOS() // Call a function to get the OS name
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
</body>
</html>
