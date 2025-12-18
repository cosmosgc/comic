@extends('layouts.app')

@section('title', 'Comics Index')

@section('meta')
    <meta name="description" content="Descubra os melhores comics da semana. Veja os comics mais populares e explore nossa coleção completa. Atualizações semanais e novos conteúdos sempre.">
    <meta name="keywords" content="comics, quadrinhos, leitura online, comics populares, melhores comics, comics da semana">

    <meta property="og:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta property="og:description" content="Veja os top comics da semana com os quadrinhos mais visualizados e explore nossa coleção completa.">
    <meta property="og:image" content="{{ asset('icon.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Comics Index - Descubra os Melhores Comics da Semana">
    <meta name="twitter:description" content="Veja os quadrinhos mais populares da semana e explore nossa vasta coleção.">
    <meta name="twitter:image" content="{{ asset('icon.jpg') }}">
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

    <!-- Top comics (desktop only) -->
    <section class="mb-10 hidden md:block">
        <h2 class="mb-4 text-xl font-semibold">
            Top comics da semana
        </h2>

        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($topComics as $comic)
                <li class="relative">
                    <span class="absolute right-2 top-2 z-10 rounded-full bg-black/70 px-2 py-1 text-xs font-semibold text-white">
                        👁 {{ $comic->view_count }}
                    </span>

                    <x-comic-card :comic="$comic" :minified="true" />
                </li>
            @endforeach
        </ul>
    </section>

    <!-- All comics -->
    <section>
        <h1 class="mb-6 text-2xl font-bold">
            Comics
        </h1>

        <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($comics as $comic)
                <x-comic-card :comic="$comic" />
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8 flex justify-center">
            {{ $comics->links() }}
        </div>
    </section>

</div>
@endsection


@section('styles')
<style>

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
        width: 100%;
        height: 100px;
        background-color: #4caf50;
        /* border-radius: 0 0 0.75rem 0.75rem; */
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
            //console.log(pageCount, currentPage);

            // Get the progress background div and set the width
            var progressBackground = card.querySelector('.progress-background');
            progressBackground.style.height = `${progress}%`;

            // Optionally change the color of the background based on progress
            if (progress < 30) {
                progressBackground.style.backgroundColor = '#ff6e6160'; // Red
            } else if (progress < 70) {
                progressBackground.style.backgroundColor = '#ff3b9360'; // Pink
            } else {
                progressBackground.style.backgroundColor = '#2a153160'; // Dark purple
            }
        });
    }

    // Call the function after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        updateComicProgress();
    });
</script>
@endsection
@push('scripts')
