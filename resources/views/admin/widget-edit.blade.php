@extends('layouts.admin-layout')

@section('title', 'Editar widget')
@section('page-title', 'Editar widget')

@section('content')
    <div class="admin-card max-w-2xl">
        <div class="admin-card-header">
            <span>Editar widget #{{ $widget->id }}</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" action="{{ route('admin.widgets.update', $widget->id) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="title" class="admin-label">Título (opcional)</label>
                    <input type="text" class="admin-input" name="title" id="title" value="{{ old('title', $widget->title) }}">
                </div>

                <div>
                    <label for="position_index" class="admin-label">Posição</label>
                    <input type="number" class="admin-input" name="position_index" id="position_index" value="{{ old('position_index', $widget->position_index) }}" required>
                </div>

                <div>
                    <label for="content" class="admin-label">Conteúdo HTML</label>
                    <textarea class="admin-input" name="content" id="content" rows="6" required>{{ old('content', $widget->content) }}</textarea>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="admin-btn admin-btn-primary">Atualizar widget</button>
                    <a href="{{ route('admin.widgets') }}" class="admin-btn admin-btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
