@extends('layouts.app')
@section('title', 'Comics Index')

@section('content')
<div class="container">
    <h1>Comics</h1>

    <div class="comics-grid">
        @foreach ($comics as $comic)
            <div class="comic-card" data-comic-id="{{ $comic->id }}" data-comic-pagecount="{{ $comic->pageCount() }}" style="background-size: 0% 100%;">
                <div class="progress-background"></div>
                <a title="{{ $comic->title }}" href="{{ route('comics.showBySlug', ['id' => $comic->id, 'slug' => $comic->slug]) }}">
                    <img src="{{ asset('storage/' . $comic->image_path) }}" alt="{{ $comic->title }}">
                    <h2>{{ Str::limit($comic->title, 35) }}</h2>
                </a>

                <p>By {{ $comic->author }}</p>
                <p>{{ Str::limit($comic->description, 100) }}</p>

                <div class="comic-tags">
                    @foreach ($comic->tags as $tag)
                        <span class="badge badge-info">{{ $tag->name }}</span>
                    @endforeach
                </div>

                <!-- You can add a total page count display if needed -->
                <p>{{ $comic->pageCount() }} paginas</p>

            </div>
        @endforeach
    </div>

    <!-- Pagination links -->
    <div class="pagination d-flex custom-pagination">
        {{ $comics->links() }}
    </div>
</div>
@endsection

@section('styles')
<style>
    .pagination {
    margin: 20px 0;
    text-align: center;
    }

    span.relative.z-0.inline-flex.rtl\:flex-row-reverse.shadow-sm.rounded-md {
        display: flex;
        flex-wrap: nowrap;
    }

    .comic-card {
        background: linear-gradient(to right, #4caf50 0%, #f0f0f0 0%);
        background-size: 0% 100%;
        transition: background-size 0.4s ease-in-out, background-position 0.6s ease-in-out, background-image 0.6s ease-in-out;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 20px;
        position: relative;
        /* Add a shadow and hover effect */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    .comic-card img {
        max-width: 100%;
        border-radius: 5px;
    }

    .comic-card:hover {
        /* Slightly elevate the card on hover for a better interactive feel */
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        transition: transform 0.5s ease, box-shadow 0.5s ease;
    }
    .progress-background {
        position: absolute;
        top: 0;
        left: 0;
        height: 0;
        width: 100%;
        background-color: #4caf50; /* default progress color */
        transition: width 0.8s ease-in-out, height 0.8s ease-in-out;
        z-index: 1; /* Place behind other content */
        border-radius: 5px 0 0 5px; /* To match the comic card's border-radius */
    }
    .comic-card a, .comic-card p, .comic-card h2, .comic-card img {
        position: relative;
        z-index: 2; /* Ensure content stays above the progress background */
    }
</style>
@endsection

@section('scripts')
<script>
    // Function to get the value of a cookie by its name
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

    // Function to update comic card background progress based on cookies
    // Function to update comic card background progress based on cookies
    function updateComicProgress() {
        // Get all comic cards
        var comicCards = document.querySelectorAll('.comic-card');

        comicCards.forEach(function(card) {
            var comicId = card.getAttribute('data-comic-id');
            var pageCount = card.getAttribute('data-comic-pagecount');
            var currentPage = getCookie('comic_' + comicId + '_page') || 0;
            currentPage = parseInt(currentPage); // Convert to integer
            if (currentPage != 0) {
                currentPage++;
            }
            pageCount = parseInt(pageCount); // Ensure pageCount is an integer

            var progress = (pageCount > 0) ? (currentPage / pageCount) * 100 : 0;

            // Get the progress background div and set the width
            var progressBackground = card.querySelector('.progress-background');
            progressBackground.style.height = progress + '%';

            // Optionally change the color of the background based on progress
            if (progress < 30) {
                progressBackground.style.backgroundColor = '#ff6f61'; // Red
            } else if (progress < 70) {
                progressBackground.style.backgroundColor = '#ff3b93'; // Pink
            } else {
                progressBackground.style.backgroundColor = '#2a1531'; // Dark purple
            }
        });
    }


    // Call the function after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        updateComicProgress();
    });
</script>
@endsection
