@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6">

    <h2 class="mb-6 text-2xl font-bold">
        Feed
    </h2>

    @auth
        <div class="mb-6">
            @include('posts.create')
        </div>
    @endauth

    <div class="space-y-6">
        @foreach ($posts as $post)
            @include('posts.post', ['post' => $post])
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="mt-8 flex justify-center">
        {{ $posts->links() }}
    </div>

</div>
@endsection
