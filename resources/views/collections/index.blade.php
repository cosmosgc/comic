@extends('layouts.app')

@section('title', 'Collections Index')

@section('content')
<div class="container">
    <h1>Collections</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="list-group">
        @foreach ($collections as $collection)
            <a href="{{ route('collections.show', $collection) }}" class="list-group-item list-group-item-action">
                {{ $collection->name }} - {{ $collection->description }}
            </a>
        @endforeach
    </div>
</div>
@endsection
