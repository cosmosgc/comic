@extends('layouts.app')
@section('content')
    <div class="container">
        <h2>Feed</h2>
        @auth
            @include('posts.create')
        @endauth
        @foreach ($posts as $post)
            @include('posts.post', ['post' => $post])
        @endforeach
        <!-- Pagination links -->
        <div class="pagination d-flex custom-pagination">
            {{ $posts->links() }}
        </div>
    </div>
@endsection
