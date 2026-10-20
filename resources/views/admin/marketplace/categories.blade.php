@extends('layouts.admin-layout')

@section('title', 'Categorias do Mercado')
@section('page-title', 'Categorias do Mercado')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm opacity-70">{{ $total }} categoria(s). Duas seções de profundidade: seção → categoria.</p>
        <a href="{{ route('admin.marketplace.categories.create') }}" class="admin-btn admin-btn-primary">Nova categoria</a>
    </div>

    <div class="space-y-4">
        @forelse ($sections as $section)
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="flex items-center gap-2">
                        <span>{{ $section->icon ?: '📦' }}</span>
                        <span>{{ $section->name }}</span>
                        <span class="admin-badge admin-badge-secondary">{{ $section->listings_count }} anúncio(s)</span>
                        @unless ($section->is_visible)
                            <span class="admin-badge admin-badge-warning">oculta</span>
                        @endunless
                    </span>
                    <span class="flex flex-wrap items-center gap-2 text-xs">
                        <a href="{{ $section->url() }}" target="_blank" rel="noopener" class="underline">ver</a>
                        <a href="{{ route('admin.marketplace.categories.edit', ['id' => $section->id]) }}" class="underline">editar</a>
                        <form method="POST" action="{{ route('admin.marketplace.categories.toggle', ['id' => $section->id]) }}">
                            @csrf
                            <button type="submit" class="underline">{{ $section->is_visible ? 'ocultar' : 'mostrar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.marketplace.categories.destroy', ['id' => $section->id]) }}"
                              onsubmit="return confirm('Excluir esta categoria?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 underline">excluir</button>
                        </form>
                    </span>
                </div>

                @if ($section->children->isNotEmpty())
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-compact">
                            <thead>
                                <tr><th>Categoria</th><th>Slug</th><th>Anúncios</th><th>Ordem</th><th>Estado</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($section->children as $child)
                                    <tr>
                                        <td>
                                            <span class="mr-1">{{ $child->icon ?: '🏷️' }}</span>
                                            {{ $child->name }}
                                        </td>
                                        <td class="font-mono text-xs opacity-70">{{ $child->slug }}</td>
                                        <td>{{ $child->listings_count }}</td>
                                        <td>{{ $child->sort_order }}</td>
                                        <td>
                                            <span class="admin-badge {{ $child->is_visible ? 'admin-badge-success' : 'admin-badge-secondary' }}">
                                                {{ $child->is_visible ? 'visível' : 'oculta' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <span class="inline-flex flex-wrap items-center justify-end gap-2 text-xs">
                                                <a href="{{ $child->url() }}" target="_blank" rel="noopener" class="underline">ver</a>
                                                <a href="{{ route('admin.marketplace.categories.edit', ['id' => $child->id]) }}" class="underline">editar</a>
                                                <form method="POST" action="{{ route('admin.marketplace.categories.toggle', ['id' => $child->id]) }}">
                                                    @csrf
                                                    <button type="submit" class="underline">{{ $child->is_visible ? 'ocultar' : 'mostrar' }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.marketplace.categories.destroy', ['id' => $child->id]) }}"
                                                      onsubmit="return confirm('Excluir esta categoria?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 underline">excluir</button>
                                                </form>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="admin-card-body text-sm opacity-60">Nenhuma subcategoria. Crie uma para organizar os anúncios.</div>
                @endif
            </div>
        @empty
            <div class="admin-card">
                <div class="admin-card-body text-center opacity-60">Nenhuma categoria ainda.</div>
            </div>
        @endforelse
    </div>
@endsection
