@extends('layouts.app')

@section('title', 'Your Profile')

@section('content')
<div class="container">
    <h1>Seu perfil</h1>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card bg-secondary text-light mb-4">
        <div class="card-body text-center">
            <h5 class="card-title">{{ $user->name }}</h5>
            <img src="{{ $user->avatar_image_path ? asset('storage/' . $user->avatar_image_path) : asset('default-avatar.png') }}"
                 alt="Avatar" class="avatar-img mb-3" />
            <!-- <p class="card-text"><strong>Email:</strong> {{ $user->email }}</p> -->
            <p class="card-text"><strong>Bio:</strong> {{ $user->bio ?? 'Sem bio disponivel' }}</p>

            <!-- Display Links -->
            @if(isset($user->links) && count($user->links) > 0)
                <div class="user-links">
                    <h6>Links:</h6>
                    <ul class="list-unstyled">
                        @foreach($user->links as $link)
                            <li>
                                <a href="{{ $link }}" target="_blank" class="link-item">{{ $link }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p>No links added.</p>
            @endif

            <a href="{{ route('profile.edit') }}" class="btn btn-primary">Editar perfil</a>
        </div>
    </div>

    <div class="mb-4">
        <h3>Suas Comics</h3>
        @if($comics->count())
            <div class="comics-grid">
                @foreach($comics as $comic)
                <div class="comic-card">
                    <a href="{{ route('comics.showBySlug', ['slug' => $comic->slug]) }}">
                        <img src="{{ asset('storage/' . $comic->image_path) }}" alt="{{ $comic->title }}">
                        <h2>{{ $comic->title }}</h2>
                    </a>
                    <p>By {{ $comic->author }}</p>
                    <p>{{ Str::limit($comic->description, 100) }}</p>
                    <div class="comic-tags">
                        @foreach ($comic->tags as $tag)
                            <span class="badge badge-info">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                    <a href="{{ route('comics.edit', ['comic' => $comic->id]) }}" class="btn btn-warning">Edit Comic</a>
                </div>
                @endforeach
            </div>

            <!-- Pagination Links -->
            <div class="pagination justify-content-center mt-4">
                {{ $comics->links() }}
            </div>
        @else
            <p>You have not uploaded any comics yet.</p>
        @endif
    </div>
</div>
@endsection

@section('styles')
<style>
    .avatar-img {
        width: 100px; /* Adjust width as needed */
        height: 100px; /* Adjust height as needed */
        border-radius: 50%;
        border: 2px solid #fff;
        margin-bottom: 15px;
    }

    .comics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    .comic-card {
        background-color: #1e1e1e;
        border: 1px solid #333;
        padding: 15px;
        text-align: center;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.5);
    }

    .comic-card img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }

    .comic-card h2 {
        font-size: 1.2em;
        margin: 10px 0;
        color: #e0e0e0;
    }

    .comic-card p {
        font-size: 0.9em;
        color: #b0b0b0;
    }

    .comic-card a {
        text-decoration: none;
        color: inherit;
    }

    .comic-card a:hover h2 {
        color: #007bff;
    }

    .user-links {
        margin: 15px 0;
    }

    .link-item {
        color: #ffffff;
        font-weight: bold;
        text-decoration: none;
    }

    .link-item:hover {
        text-decoration: underline;
    }
</style>
@endsection
