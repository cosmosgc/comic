@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Comics</h1>
aaa
    <div class="comics-grid">
        @foreach ($comics as $comic)
            <div class="comic-card">
                {{dd($comic);}}
                <a href="{{ route('comics.show', $comic->id) }}">
                    <img src="{{ asset($comic->image_path) }}" alt="{{ $comic->title }}">
                    <h2>{{ $comic->title }}</h2>
                </a>
                <p>By {{ $comic->author }}</p>
                <p>{{ Str::limit($comic->description, 100) }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection

<!-- Custom CSS to handle the grid layout -->
@section('styles')
<style>
    .comics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .comic-card {
        border: 1px solid #ddd;
        padding: 15px;
        text-align: center;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .comic-card img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }

    .comic-card h2 {
        font-size: 1.2em;
        margin: 10px 0;
    }

    .comic-card p {
        font-size: 0.9em;
        color: #555;
    }

    .comic-card a {
        text-decoration: none;
        color: inherit;
    }

    .comic-card a:hover h2 {
        color: #007bff;
    }
</style>
@endsection
