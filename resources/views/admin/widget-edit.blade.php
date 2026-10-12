@extends('layouts.admin-layout')

@section('title', 'Edit Widget')
@section('page-title', 'Edit Widget')

@section('content')
    <div class="admin-card max-w-2xl">
        <div class="admin-card-header">
            <span>Edit Widget #{{ $widget->id }}</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.widgets.update', $widget->id) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="title" class="admin-label">Title (optional)</label>
                    <input type="text" class="admin-input" name="title" id="title" value="{{ old('title', $widget->title) }}">
                </div>

                <div>
                    <label for="position_index" class="admin-label">Position Index</label>
                    <input type="number" class="admin-input" name="position_index" id="position_index" value="{{ old('position_index', $widget->position_index) }}" required>
                </div>

                <div>
                    <label for="content" class="admin-label">HTML Content</label>
                    <textarea class="admin-input" name="content" id="content" rows="6" required>{{ old('content', $widget->content) }}</textarea>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="admin-btn admin-btn-primary">Update Widget</button>
                    <a href="{{ route('admin.widgets') }}" class="admin-btn admin-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
