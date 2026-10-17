@extends('layouts.admin-layout')

@section('title', $category->exists ? 'Editar categoria' : 'Nova categoria')
@section('page-title', $category->exists ? 'Editar categoria' : 'Nova categoria')

@section('content')
    <form method="POST"
          action="{{ $category->exists ? route('admin.forum.categories.update', $category->id) : route('admin.forum.categories.store') }}"
          class="admin-card max-w-2xl">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="admin-card-body space-y-4">
            <div>
                <label class="admin-label" for="name">Nome</label>
                <input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="100" class="admin-input w-full">
                @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="admin-label" for="slug">Slug (opcional)</label>
                    <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" maxlength="100" class="admin-input w-full" placeholder="auto">
                    @error('slug')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="admin-label" for="parent_id">Seção pai</label>
                    <select id="parent_id" name="parent_id" class="admin-input w-full">
                        <option value="">— é uma seção —</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((int) old('parent_id', $category->parent_id) === $section->id)>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="admin-label" for="description">Descrição</label>
                <textarea id="description" name="description" rows="2" maxlength="1000" class="admin-input w-full">{{ old('description', $category->description) }}</textarea>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="admin-label" for="icon">Ícone</label>
                    <input id="icon" name="icon" value="{{ old('icon', $category->icon) }}" maxlength="16" class="admin-input w-full" placeholder="💬">
                </div>
                <div>
                    <label class="admin-label" for="color">Cor</label>
                    <input id="color" name="color" value="{{ old('color', $category->color) }}" maxlength="32" class="admin-input w-full" placeholder="#6366f1">
                </div>
                <div>
                    <label class="admin-label" for="sort_order">Ordem</label>
                    <input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" class="admin-input w-full">
                </div>
            </div>

            <div>
                <label class="admin-label" for="min_role">Quem pode criar tópicos</label>
                <select id="min_role" name="min_role" class="admin-input w-full">
                    @foreach ($roles as $key => $label)
                        <option value="{{ $key }}" @selected(old('min_role', $category->min_role ?? 'member') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $category->is_visible ?? true))> Visível
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_locked" value="1" @checked(old('is_locked', $category->is_locked ?? false))> Trancado
                </label>
            </div>
        </div>

        <div class="admin-card-body flex items-center gap-3 border-t border-zinc-200 dark:border-zinc-800">
            <button type="submit" class="admin-btn admin-btn-primary">Salvar</button>
            <a href="{{ route('admin.forum.categories') }}" class="admin-btn admin-btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection
