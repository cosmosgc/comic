@extends('layouts.admin-layout')

@section('title', $category->exists ? 'Editar categoria' : 'Nova categoria')
@section('page-title', $category->exists ? 'Editar categoria' : 'Nova categoria do Mercado')

@section('content')
    <form method="POST"
          action="{{ $category->exists ? route('admin.marketplace.categories.update', ['id' => $category->id]) : route('admin.marketplace.categories.store') }}"
          class="admin-card max-w-3xl">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif

        <div class="admin-card-body space-y-4">
            @if ($errors->any())
                <div class="admin-alert admin-alert-danger">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="admin-label" for="name">Nome</label>
                    <input id="name" name="name" value="{{ old('name', $category->name) }}" class="admin-input w-full" required maxlength="100">
                </div>
                <div>
                    <label class="admin-label" for="slug">Slug (opcional)</label>
                    <input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="admin-input w-full" maxlength="120"
                           placeholder="gerado a partir do nome">
                </div>
                <div>
                    <label class="admin-label" for="parent_id">Seção</label>
                    <select id="parent_id" name="parent_id" class="admin-input w-full">
                        <option value="">— É uma seção de primeiro nível —</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((int) old('parent_id', $category->parent_id) === $section->id)>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="admin-label" for="sort_order">Ordem</label>
                    <input id="sort_order" type="number" name="sort_order" min="0" max="9999"
                           value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="admin-input w-full">
                </div>
                <div>
                    <label class="admin-label" for="icon">Ícone (emoji)</label>
                    <input id="icon" name="icon" value="{{ old('icon', $category->icon) }}" class="admin-input w-full" maxlength="16" placeholder="🏷️">
                </div>
                <div>
                    <label class="admin-label" for="color">Cor (hex)</label>
                    <input id="color" name="color" value="{{ old('color', $category->color) }}" class="admin-input w-full" maxlength="20" placeholder="#f59e0b">
                </div>
            </div>

            <div>
                <label class="admin-label" for="description">Descrição</label>
                <textarea id="description" name="description" rows="3" class="admin-input w-full" maxlength="1000">{{ old('description', $category->description) }}</textarea>
            </div>

            <input type="hidden" name="is_visible" value="0">
            <label class="admin-check">
                <input type="checkbox" name="is_visible" value="1" @checked((bool) old('is_visible', $category->is_visible ?? true))>
                Visível no Mercado
            </label>
        </div>

        <div class="admin-card-body flex items-center gap-3 border-t border-zinc-200 dark:border-zinc-800">
            <button type="submit" class="admin-btn admin-btn-primary">Salvar categoria</button>
            <a href="{{ route('admin.marketplace.categories') }}" class="admin-btn admin-btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection
