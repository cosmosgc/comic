@extends('layouts.admin-layout')

@section('title', 'Edit Widget')
@section('page-title', 'Edit Widget')

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-header">Edit Widget #{{ $widget->id }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.widgets.update', $widget->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="title" class="form-label">Title (optional)</label>
                        <input type="text" class="form-control" name="title" id="title" value="{{ old('title', $widget->title) }}">
                    </div>

                    <div class="mb-3">
                        <label for="position_index" class="form-label">Position Index</label>
                        <input type="number" class="form-control" name="position_index" id="position_index" value="{{ old('position_index', $widget->position_index) }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">HTML Content</label>
                        <textarea class="form-control" name="content" id="content" rows="6" required>{{ old('content', $widget->content) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Widget</button>
                    <a href="{{ route('admin.widgets') }}" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
@endsection
