@extends('layouts.app')
@section('title', 'Comics Index')

@section('content')
<div class="container">
    <h1>Comics</h1>

    <div class="comics-grid">
        @foreach ($comics as $comic)
            <div class="comic-card" data-comic-id="{{ $comic->id }}" data-comic-pagecount="{{ $comic->pageCount() }}" style="background-size: 0% 100%;">
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
        transition: background-size 0.4s ease-in-out;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 20px;
        position: relative;
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
    function updateComicProgress() {
        // Get all comic cards
        var comicCards = document.querySelectorAll('.comic-card');

        comicCards.forEach(function(card) {
            var comicId = card.getAttribute('data-comic-id');
            var pageCount = card.getAttribute('data-comic-pagecount');
            //var progress = getCookie('comic_' + comicId + '_page') || 0; // Default to 0 if no cookie is found
            var currentPage = getCookie('comic_' + comicId + '_page') || 0;
            currentPage = parseInt(currentPage); // Convert to integer
            if(currentPage != 0){
                currentPage++;
            }
            pageCount = parseInt(pageCount); // Ensure pageCount is an integer

            var progress = (pageCount > 0) ? (currentPage / pageCount) * 100 : 0;

            // Set the background size based on progress (0% to 100%)
            var backgroundSize = progress + '% 100%';
            card.style.backgroundSize = backgroundSize;
            console.log(comicId, progress, currentPage,pageCount)
            // Optionally change the gradient color
            if (progress < 30) {
                card.style.backgroundImage = 'linear-gradient(to bottom, #ff6f61 ' + progress + '%, #343a40 ' + progress + '%)';
            } else if (progress < 70) {
                card.style.backgroundImage = 'linear-gradient(to bottom, #ff3b93 ' + progress + '%, #343a40 ' + progress + '%)';
            } else {
                card.style.backgroundImage = 'linear-gradient(to bottom, #2a1531 ' + progress + '%, #343a40 ' + progress + '%)';
            }
        });
    }

    // Call the function after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        updateComicProgress();
    });
</script>
@endsection
