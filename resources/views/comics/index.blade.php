@extends('layouts.app')
@section('title', 'Comics Index')

@section('content')
<div class="container">
    <h1>Comics</h1>

    <div class="comics-grid">
        @foreach ($comics as $comic)
            <div class="comic-card">
                <a href="{{ route('comics.show', $comic->id) }}">
                    <img src="{{ asset('storage/' . $comic->image_path) }}" alt="{{ $comic->title }}">
                    <h2>{{ $comic->title }}</h2>
                </a>
                <p>By {{ $comic->author }}</p>
                <p>{{ Str::limit($comic->description, 100) }}</p>
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
</style>
@endsection
