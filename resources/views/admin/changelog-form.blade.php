@extends('layouts.admin-layout')

@section('title', isset($entryId) ? 'Editar entrada do changelog' : 'Nova entrada do changelog')
@section('page-title', isset($entryId) ? 'Editar entrada' : 'Nova entrada')

@section('content')
    @if ($errors->any())
        <div class="admin-alert admin-alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="admin-card max-w-3xl">
        <div class="admin-card-body">
            @if (isset($entryId))
                <form method="POST" action="{{ route('admin.changelogs.update', $entryId) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
            @else
                <form method="POST" action="{{ route('admin.changelogs.store') }}" class="space-y-4">
                    @csrf
            @endif

                <div>
                    <label for="f-title" class="admin-label">Título *</label>
                    <input type="text" class="admin-input" id="f-title" name="title" maxlength="200" required
                           value="{{ old('title', $entry['title'] ?? '') }}">
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                    <div class="sm:col-span-3">
                        <label for="f-date" class="admin-label">Data *</label>
                        <input type="date" class="admin-input" id="f-date" name="date" required
                               value="{{ old('date', $entry['date'] ?? date('Y-m-d')) }}">
                    </div>
                    <div class="sm:col-span-3">
                        <label for="f-category" class="admin-label">Categoria *</label>
                        <select class="admin-input" id="f-category" name="category">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"
                                    {{ old('category', $entry['category'] ?? 'Added') === $category ? 'selected' : '' }}>
                                    {{ $category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="f-pr" class="admin-label">PR #</label>
                        <input type="number" class="admin-input" id="f-pr" name="pr" min="1"
                               value="{{ old('pr', $entry['pr'] ?? '') }}" placeholder="—">
                    </div>
                    <div class="sm:col-span-4">
                        <label for="f-tags" class="admin-label">Tags <span class="admin-muted">(separadas por vírgula)</span></label>
                        <input type="text" class="admin-input" id="f-tags" name="tags"
                               value="{{ old('tags', isset($entry) ? implode(', ', $entry['tags']) : '') }}"
                               placeholder="upload, ftp">
                    </div>
                </div>

                <div>
                    <label for="f-summary" class="admin-label">Resumo <span class="admin-muted">(uma linha, máx 500)</span></label>
                    <textarea class="admin-input" id="f-summary" name="summary" rows="2" maxlength="500">{{ old('summary', $entry['summary'] ?? '') }}</textarea>
                </div>

                <div>
                    <label for="f-body" class="admin-label">Texto <span class="admin-muted">(Markdown, estilo GitHub)</span></label>
                    <textarea class="admin-input font-mono" id="f-body" name="body" rows="10">{{ old('body', $entry['body'] ?? '') }}</textarea>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="admin-btn admin-btn-primary">Salvar entrada</button>
                    <a href="{{ route('admin.changelogs') }}" class="admin-btn admin-btn-secondary">Voltar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
