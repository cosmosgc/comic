@extends('layouts.app')

@section('title', $collection->name)

@section('content')
<div class="container">
    <h1>{{ $collection->name }}</h1>
    <p>{{ $collection->description }}</p>

    <h2>Comics dessa coleção</h2>
    <div style="
    display: flex;
    gap: 10px;
">
        @foreach ($collection->comics as $comic)
            <div class="col-md-4 mb-3 comic-card bg-dark" data-id="{{ $comic->id }}">
                <div class="card bg-dark">
                    <img src="{{ asset('storage/' . $comic->image_path) }}" class="card-img-top" alt="{{ $comic->title }}">
                    <div class="card-body">
                        <h5 class="card-title">{{ $comic->title }}</h5>
                    </div>
                </div>
            </div>

        @endforeach
    </div>

    <a href="{{ route('collections.edit', $collection) }}" class="btn btn-primary">Edit Collection</a>
</div>
@endsection
