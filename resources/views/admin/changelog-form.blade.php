@extends('layouts.admin-layout')

@section('title', isset($entryId) ? 'Edit changelog entry' : 'New changelog entry')
@section('page-title', isset($entryId) ? 'Edit entry' : 'New entry')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            @if (isset($entryId))
                <form method="POST" action="{{ route('admin.changelogs.update', $entryId) }}">
                    @csrf
                    @method('PUT')
            @else
                <form method="POST" action="{{ route('admin.changelogs.store') }}">
                    @csrf
            @endif

                <div class="form-group">
                    <label for="f-title">Title *</label>
                    <input type="text" class="form-control" id="f-title" name="title" maxlength="200" required
                           value="{{ old('title', $entry['title'] ?? '') }}">
                </div>

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="f-date">Date *</label>
                        <input type="date" class="form-control" id="f-date" name="date" required
                               value="{{ old('date', $entry['date'] ?? date('Y-m-d')) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="f-category">Category *</label>
                        <select class="form-control" id="f-category" name="category">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"
                                    {{ old('category', $entry['category'] ?? 'Added') === $category ? 'selected' : '' }}>
                                    {{ $category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label for="f-pr">PR #</label>
                        <input type="number" class="form-control" id="f-pr" name="pr" min="1"
                               value="{{ old('pr', $entry['pr'] ?? '') }}" placeholder="—">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="f-tags">Tags <small class="text-muted">(comma separated)</small></label>
                        <input type="text" class="form-control" id="f-tags" name="tags"
                               value="{{ old('tags', isset($entry) ? implode(', ', $entry['tags']) : '') }}"
                               placeholder="upload, ftp">
                    </div>
                </div>

                <div class="form-group">
                    <label for="f-summary">Summary <small class="text-muted">(one-liner, max 500)</small></label>
                    <textarea class="form-control" id="f-summary" name="summary" rows="2" maxlength="500">{{ old('summary', $entry['summary'] ?? '') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="f-body">Body <small class="text-muted">(Markdown, GitHub style)</small></label>
                    <textarea class="form-control" id="f-body" name="body" rows="10">{{ old('body', $entry['body'] ?? '') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save entry</button>
                <a href="{{ route('admin.changelogs') }}" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
@endsection
