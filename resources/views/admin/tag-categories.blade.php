@extends('layouts.admin-layout')

@section('title', 'Categorias de tags')
@section('page-title', 'Categorias de tags')

@section('content')
    @if (session('success'))
        <div class="admin-card mb-6 border-emerald-500/30 bg-emerald-500/10">
            <div class="admin-card-body text-sm text-emerald-700 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <span>Como funciona</span>
        </div>
        <div class="admin-card-body text-sm opacity-80">
            <p>
                Toda tag recebe uma categoria automaticamente: primeiro pelo
                <strong>namespace</strong> (<code>artist:nome</code> → Artist,
                <code>parody:x</code> → Copyright), depois por
                <strong>palavras-chave</strong> (ex.: <code>pokemon</code> → Copyright),
                e o resto cai em <strong>General</strong>. As cores pintam as tags
                no site, no editor e na busca da E-Hentai.
            </p>
            <p class="mt-2">
                Depois de ajustar palavras-chave, namespaces ou cores, use
                <strong>Recategorizar todas</strong> para reaplicar em todas as tags existentes.
                Imports com namespace ensinam a categoria sozinhos
                (<code>male:dog boy</code> adiciona <code>dog boy</code> ao vocabulário
                aprendido de Male, que tem prioridade na resolução).
                @if ($uncategorized > 0)
                    <span class="font-semibold">{{ $uncategorized }} tag(s) sem categoria.</span>
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.tag-categories.create') }}" class="admin-btn admin-btn-success admin-btn-sm">+ Nova categoria</a>
                <form action="{{ route('admin.tag-categories.recategorize') }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn-warning admin-btn-sm">Recategorizar todas as tags</button>
                </form>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <span>Todas as categorias ({{ count($categories) }})</span>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Prévia</th>
                        <th>Nome</th>
                        <th>Cores</th>
                        <th>Namespaces</th>
                        <th>Palavras-chave</th>
                        <th>Tags</th>
                        <th>Visível</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                      style="background-color: {{ $category->background }}; border: 1px solid {{ $category->border }}; color: {{ $category->text_color }};">
                                    exemplo
                                </span>
                            </td>
                            <td class="font-medium">
                                {{ $category->label }}
                                <code class="opacity-60">{{ $category->name }}</code>
                            </td>
                            <td class="whitespace-nowrap">
                                <code>{{ $category->background }}</code>
                                <code>{{ $category->border }}</code>
                                <code>{{ $category->text_color }}</code>
                            </td>
                            <td class="max-w-48 truncate" title="{{ implode(', ', $category->namespaces ?? []) }}">
                                {{ implode(', ', $category->namespaces ?? []) ?: '—' }}
                            </td>
                            <td class="max-w-64 truncate" title="{{ implode(', ', $category->keywords ?? []) }}">
                                {{ implode(', ', $category->keywords ?? []) ?: '—' }}
                                @if (! empty($category->learned_keywords))
                                    <br>
                                    <span class="opacity-60" title="Aprendidas de imports (têm prioridade). Botão Limpar abaixo.">+ {{ implode(', ', $category->learned_keywords) }}</span>
                                @endif
                            </td>
                            <td>{{ $category->tags_count }}</td>
                            <td>
                                @if ($category->is_visible)
                                    <span class="admin-badge admin-badge-primary">Visível</span>
                                @else
                                    <span class="admin-badge admin-badge-secondary">Oculta</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <form action="{{ route('admin.tag-categories.toggle', $category->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="admin-btn admin-btn-sm">{{ $category->is_visible ? 'Ocultar' : 'Mostrar' }}</button>
                                </form>
                                @if (! empty($category->learned_keywords))
                                    <form action="{{ route('admin.tag-categories.clear-learned', $category->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apagar o vocabulário aprendido de {{ $category->label }}?')">
                                        @csrf
                                        <button type="submit" class="admin-btn admin-btn-sm">Limpar aprendidas</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.tag-categories.edit', $category->id) }}" class="admin-btn admin-btn-warning admin-btn-sm">Editar</a>
                                <form action="{{ route('admin.tag-categories.destroy', $category->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Excluir a categoria {{ $category->label }}? As tags voltam para General.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
