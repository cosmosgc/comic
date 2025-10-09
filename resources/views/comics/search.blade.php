@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Search results for: <strong>{{ $searchTerm }}</strong></h2>
    <div class="comics-grid highlight-comics">
        @if ($comics->count() > 0)
            @foreach ($comics as $comic)
                <li class="mb-3">
                    <div class="mb-1 text-muted">
                        Views: {{ $comic->view_count }}
                    </div>
                    <x-comic-card :comic="$comic" :minified="false" />
                </li>
            @endforeach

            {{-- Pagination --}}
            
        @else
            <p>No comics found for this search.</p>
        @endif
    </div>
    <div class="mt-4">
                {{ $comics->links() }}
            </div>
</div>
@endsection
