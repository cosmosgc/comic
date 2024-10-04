@extends('layouts.app')

@section('title', 'Comics Index')

@section('meta')
    <meta name="description" content="Descubra os melhores comics da semana. Veja os comics mais populares e explore nossa coleção completa. Atualizações semanais e novos conteúdos sempre.">
    <meta name="keywords" content="comics, quadrinhos, leitura online, comics populares, melhores comics, comics da semana">
    <meta property="og:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta property="og:description" content="Veja os top comics da semana com os quadrinhos mais visualizados e explore nossa coleção completa.">
    <meta property="og:image" content="{{ asset('path/to/featured-image.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta name="twitter:description" content="Veja os quadrinhos mais populares da semana e explore nossa vasta coleção.">
    <meta name="twitter:image" content="{{ asset('path/to/featured-image.jpg') }}">
@endsection

@section('content')
<head>
<meta name="description" content="Descubra os melhores comics da semana. Veja os comics mais populares e explore nossa coleção completa. Atualizações semanais e novos conteúdos sempre.">
    <meta name="keywords" content="comics, quadrinhos, leitura online, comics populares, melhores comics, comics da semana">
    <meta property="og:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta property="og:description" content="Veja os top comics da semana com os quadrinhos mais visualizados e explore nossa coleção completa.">
    <meta property="og:image" content="{{ asset('path/to/featured-image.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta name="twitter:description" content="Veja os quadrinhos mais populares da semana e explore nossa vasta coleção.">
    <meta name="twitter:image" content="{{ asset('path/to/featured-image.jpg') }}">
</head>
<div class="container">
    <h3>Top comics da semana</h3>
    <div class="comics-grid highlight-comics">
        @foreach ($topComics as $comic)
            <li>
                Views: {{ $comic->view_count }}
                <x-comic-card :comic="$comic" :minified="true" />
            </li>
        @endforeach
    </div>
    <h1>Comics</h1>
    <div class="comics-grid">
        @foreach ($comics as $comic)
            <x-comic-card :comic="$comic" />
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
        z-index: -2; /* Place behind other content */
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
