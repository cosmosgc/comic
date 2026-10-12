@extends('layouts.admin-layout')

@section('title', 'Widgets')
@section('page-title', 'Widgets')

@section('content')
    {{-- Create Widget Form --}}
    <div class="admin-card mb-6 max-w-2xl">
        <div class="admin-card-header">
            <span>Create New Widget</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.widgets.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="title" class="admin-label">Title (optional)</label>
                    <input type="text" class="admin-input" name="title" id="title">
                </div>

                <div>
                    <label for="position_index" class="admin-label">Position Index</label>
                    <input type="number" class="admin-input" name="position_index" id="position_index" required>
                </div>

                <div>
                    <label for="content" class="admin-label">HTML Content</label>
                    <textarea class="admin-input" name="content" id="content" rows="4" required></textarea>
                </div>

                <button type="submit" class="admin-btn admin-btn-success">Create Widget</button>
            </form>
        </div>
    </div>

    {{-- Widget List --}}
    <h2 class="mb-3 text-base font-semibold">All Widgets ({{ count($widgets) }})</h2>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @foreach ($widgets as $widget)
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="truncate">{{ $widget->title ?? 'Untitled Widget' }}</span>
                    <span class="admin-badge admin-badge-primary">Position {{ $widget->position_index }}</span>
                </div>
                <div class="admin-card-body space-y-3">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                        {!! $widget->content !!}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.widgets.edit', $widget->id) }}" class="admin-btn admin-btn-warning admin-btn-sm">Edit</a>
                        <form action="{{ route('admin.widgets.destroy', $widget->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this widget?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
