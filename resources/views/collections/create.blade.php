@extends('layouts.app')

@section('title', 'Create Collection')

@section('content')
<div class="container">
    <h1>Create Collection</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('collections.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Collection Name</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control"></textarea>
        </div>

        <div class="form-group">
            <label for="comics">Select Comics</label>
            <select name="comics[]" id="comics" class="form-control" multiple>
                @foreach ($comics as $comic)
                    <option value="{{ $comic->id }}">{{ $comic->title }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Create Collection</button>
    </form>
</div>
@endsection
