@extends('layouts.admin-layout')

@section('title', 'Widgets')
@section('page-title', 'Widgets Management')

@section('content')
    <div class="container">
        <h2 class="mb-4">All Widgets</h2>

        {{-- Create Widget Form --}}
        <div class="card mb-4">
            <div class="card-header">Create New Widget</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.widgets.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">Title (optional)</label>
                        <input type="text" class="form-control" name="title" id="title">
                    </div>

                    <div class="mb-3">
                        <label for="position_index" class="form-label">Position Index</label>
                        <input type="number" class="form-control" name="position_index" id="position_index" required>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">HTML Content</label>
                        <textarea class="form-control" name="content" id="content" rows="4" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Create Widget</button>
                </form>
            </div>
        </div>

        {{-- Widget List --}}
        <div class="row row-cols-1 row-cols-md-2 g-4">
            @foreach ($widgets as $widget)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>{{ $widget->title ?? 'Untitled Widget' }}</strong>
                            <span class="badge bg-primary">Position {{ $widget->position_index }}</span>
                        </div>
                        <div class="card-body">
                            <div class="border p-2 mb-2" style="background-color: #f8f9fa;">
                                {!! $widget->content !!}
                            </div>

                            <a href="{{ route('admin.widgets.edit', $widget->id) }}" class="btn btn-sm btn-warning">Edit</a>
                            <form action="{{ route('admin.widgets.destroy', $widget->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Delete this widget?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
