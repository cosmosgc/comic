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
            color: #fff;
            font-family: sans-serif;
        }
        .comic-header {
            padding: 20px;
            background-color: #2a2a2a;
            text-align: center;
        }
        .comic-header h1 {
            margin: 0;
            font-size: 2em;
        }
        .comic-meta {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .tags, .collections {
            margin: 10px 0;
        }
        .tag, .collection {
            display: inline-block;
            background-color: #444;
            color: #fff;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 5px;
            font-size: 0.8em;
        }
        .comic-pages {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .comic-pages img {
            max-width: 100%;
            height: auto;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .progress-indicator {
        position: fixed;
        top: 10px;
        right: 10px;
        background-color: rgba(50, 50, 50, 0.8);
        padding: 8px 12px;
        border-radius: 5px;
        font-size: 0.9em;
        z-index: 1000;
        }
        .back-button {
            position: fixed;
            top: 10px;
            left: 10px;
            background-color: rgba(50, 50, 50, 0.8);
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #fff;
            text-decoration: none;
            z-index: 1000;
        }
        .back-button:hover {
            background-color: rgba(80, 80, 80, 0.8);
        }
        .back-to-top-button {
            position: fixed;
            background-color: #444;
            color: white;
            border: none;
            
            top: 10px;
            left: 100px;
            background-color: rgba(50, 50, 50, 0.8);
            padding: 6px 12px;
            margin-left: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .back-to-top-button:hover {
            background-color: #666;
        }

        .magnified {
            width: 100vw;
            height: auto;
            max-height: none;
            object-fit: contain;
            cursor: zoom-out;
            transition: width 0.3s ease, transform 0.3s ease;
            z-index: 1000;
            position: relative;
        }

    </style>
    
</head>
<body>
    
<a href="{{ url()->previous() }}" class="back-button">← Back</a>
<button onclick="topFunction()" id="backToTopButton" class="back-to-top-button">↑ Top</button>
<div class="progress-indicator" id="progress">Page 1 of {{ $comic->pages->count() }}</div>

<div class="comic-header">
    <h1>{{ $comic->title }}</h1>
    <div class="comic-meta">
        <p>By {{ $comic->author ?? 'Unknown Author' }}</p>
        @if ($comic->user)
            <div class="uploader-info">
                <h5 class="card-title">{{ $comic->user->name }}</h5>
                <img 
                    src="{{ $comic->user->avatar_image_path ? asset($comic->user->avatar_image_path) : asset('default-avatar.png') }}" 
                    alt="{{ $comic->user->name }}'s avatar" 
                    class="avatar-image"
                    style="max-width: 100px; border-radius: 50%;"
                >
            </div>
        @else
            <p>Uploaded By Unknown Uploader</p>
        @endif
        
        <p>{{ $comic->description }}</p>
        <p>Views: {{ $comic->view_count }}</p>
    </div>
    @if($comic->tags->count())
        <div class="tags">
            <strong>Tags:</strong>
            @foreach($comic->tags as $tag)
                <span class="tag">{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif
    @if($comic->collections->count())
        <div class="collections">
            <strong>Collections:</strong>
            @foreach($comic->collections as $collection)
                <span class="collection">{{ $collection->name }}</span>
            @endforeach
        </div>
    @endif
</div>

<div class="comic-pages">
    @foreach ($comic->pages as $page)
        <a id="page-{{ $page->page_number }}"></a>
        <img 
            src="{{ asset('storage/' . $page->image_path) }}" 
            data-page="{{ $page->page_number }}"
            id="page-{{ $page->page_number }}"
            alt="Page {{ $page->page_number }}"
            loading="lazy"
        >
    @endforeach
</div>


<script>
        let pages = 0;
        let totalPages = 1;
        let progress = document.getElementById('progress');
        var comicId = '{{ $comic->id }}'; // Get comic ID from the backend
        var storedPage = getCookie("comic_" + comicId + "_page");



    document.addEventListener('DOMContentLoaded', () => {
         pages = Array.from(document.querySelectorAll('.comic-pages img'));
         totalPages = pages.length;
         progress = document.getElementById('progress');
         scrollToPage(storedPage);

        
    });
    function topFunction() {
     window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

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

    function updateProgress(currentPage) {
            setCookie("comic_" + comicId + "_page", currentPage, 30);
            progress.textContent = `Page ${currentPage} of ${totalPages}`;
        }

        function scrollToPage(pageNumber) {
            const anchor = document.getElementById(`page-${pageNumber}`);
            if (anchor) {
                window.location.hash = `page-${pageNumber}`;
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                updateProgress(pageNumber);
            }
        }

        function getCurrentPage() {
            let closest = {page: 1, offset: Infinity};
            const scrollY = window.scrollY;
            pages.forEach(img => {
                const rect = img.getBoundingClientRect();
                const offset = Math.abs(rect.top);
                const page = parseInt(img.dataset.page) || 1;
                if (offset < closest.offset) {
                    closest = {page, offset};
                }
            });
            return closest.page;
        }

        // Initial load
        const hash = window.location.hash;
        if (hash.startsWith('#page-')) {
            const pageNumber = parseInt(hash.replace('#page-', ''));
            if (!isNaN(pageNumber)) {
                scrollToPage(pageNumber);
            }
        }

        // Update progress on scroll
        window.addEventListener('scroll', () => {
            const currentPage = getCurrentPage();
            updateProgress(currentPage);
        });

        // Keyboard navigation
let scrollInterval = null;
const scrollSpeed = 15; // pixels per frame (adjust for desired speed)
let isMagnified = false;
let magnifiedImg = null;

function getCurrentImageElement() {
    let closest = {page: null, offset: Infinity, element: null};
    pages.forEach(img => {
        const rect = img.getBoundingClientRect();
        const offset = Math.abs(rect.top);
        const page = parseInt(img.dataset.page);
        if (offset < closest.offset) {
            closest = {page, offset, element: img};
        }
    });
    return closest.element;
}

function toggleMagnify(img) {
    console.log(img);
    if (!img) return;
    if (isMagnified) {
        img.classList.remove('magnified');
        isMagnified = false;
        magnifiedImg = null;
    } else {
        img.classList.add('magnified');
        isMagnified = true;
        magnifiedImg = img;
        img.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}

document.querySelectorAll('.comic-pages img').forEach(img => {
    img.addEventListener('click', () => {
        if (isMagnified && magnifiedImg === img) {
            toggleMagnify(img);
        } else {
            if (isMagnified && magnifiedImg) {
                toggleMagnify(magnifiedImg);
            }
            toggleMagnify(img);
        }
    });
});


function startScroll(direction) {
    if (scrollInterval) return; // prevent multiple intervals
    scrollInterval = setInterval(() => {
        window.scrollBy(0, direction * scrollSpeed);
    }, 16); // roughly 60fps
}

function stopScroll() {
    if (scrollInterval) {
        clearInterval(scrollInterval);
        scrollInterval = null;
    }
}

document.addEventListener('keydown', (e) => {
    if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
    const key = e.key.toLowerCase();
    const currentPage = getCurrentPage();

    if (e.key === 'ArrowRight' || e.key.toLowerCase() === 'd') {
        // Next page
        if (currentPage < totalPages) {
            scrollToPage(currentPage + 1);
        }
    }

    if (e.key === 'ArrowLeft' || e.key.toLowerCase() === 'a') {
        // Previous page
        if (currentPage > 1) {
            scrollToPage(currentPage - 1);
        }
    }
    if (key === 'w') {
        startScroll(-1);
    }
    if (key === 's') {
        startScroll(1);
    }
    if (key === 'f') {
        const currentImg = getCurrentImageElement();
        if (isMagnified && magnifiedImg) {
            toggleMagnify(magnifiedImg);
        } else {
            toggleMagnify(currentImg);
        }
    }
    const navKeys = ['w', 'a', 's', 'd', 'arrowright', 'arrowleft', 'arrowup', 'arrowdown'];
    if (navKeys.includes(e.key.toLowerCase())) {
        if (isMagnified && magnifiedImg) {
            toggleMagnify(magnifiedImg);
        }
    }

});

document.addEventListener('keyup', (e) => {
    const key = e.key.toLowerCase();
    if (key === 'w' || key === 's') {
        stopScroll();
    }
});

</script>
</body>
</html>
