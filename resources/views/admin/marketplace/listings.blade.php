@extends('layouts.admin-layout')

@section('title', 'Anúncios do Mercado')
@section('page-title', 'Anúncios do Mercado')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.marketplace.listings') }}" class="admin-card mb-4">
        <div class="admin-card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="admin-label" for="q">Buscar</label>
                <input id="q" name="q" value="{{ $query }}" class="admin-input w-full" placeholder="título ou vendedor">
            </div>
            <div>
                <label class="admin-label" for="status">Estado</label>
                <select id="status" name="status" class="admin-input w-full">
                    @foreach (['all' => 'Todos', 'pending' => 'Aguardando aprovação', 'active' => 'Ativos', 'reserved' => 'Reservados', 'draft' => 'Rascunhos', 'sold' => 'Vendidos', 'expired' => 'Expirados', 'hidden' => 'Ocultos', 'removed' => 'Removidos'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="category">Categoria</label>
                <select id="category" name="category" class="admin-input w-full">
                    <option value="">Todas</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option->id }}" @selected((int) $category === $option->id)>
                            {{ $option->parent_id ? '— ' : '' }}{{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="kind">Tipo</label>
                <select id="kind" name="kind" class="admin-input w-full">
                    <option value="">Todos</option>
                    @foreach ($kinds as $key => $label)
                        <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
                <a href="{{ route('admin.marketplace.listings') }}" class="admin-btn admin-btn-secondary">Limpar</a>
            </div>
        </div>
    </form>

    <div class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead>
                    <tr>
                        <th>Anúncio</th>
                        <th>Vendedor</th>
                        <th>Categoria</th>
                        <th>Estado</th>
                        <th>Preço</th>
                        <th>Visitas</th>
                        <th>Atualizado</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($listings as $listing)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if ($listing->coverUrl())
                                        <img src="{{ $listing->coverUrl() }}" alt="" class="h-10 w-10 rounded object-cover">
                                    @endif
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">
                                            @if ($listing->trashed())
                                                {{ $listing->title }}
                                            @else
                                                <a href="{{ route('marketplace.show', ['listing' => $listing->slug]) }}" target="_blank" rel="noopener" class="hover:underline">
                                                    {{ $listing->title }}
                                                </a>
                                            @endif
                                        </div>
                                        <div class="text-xs opacity-60">
                                            {{ $listing->kindLabel() }} · {{ \App\Models\MarketplaceListing::typeOptions()[$listing->listing_type] ?? $listing->listing_type }}
                                            @if ($listing->is_featured)
                                                · <span class="admin-badge admin-badge-primary">destaque</span>
                                            @endif
                                            @if ($listing->trashed())
                                                · <span class="admin-badge admin-badge-danger">removido</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $listing->seller?->name ?? '—' }}</td>
                            <td>{{ $listing->category?->name ?? '—' }}</td>
                            <td>
                                <span class="admin-badge {{ $listing->isPubliclyVisible() ? 'admin-badge-success' : 'admin-badge-secondary' }}">
                                    {{ $listing->statusLabel() }}
                                </span>
                                @unless ($listing->is_approved)
                                    <span class="admin-badge admin-badge-warning">pendente</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap">{{ $listing->priceShort() }}</td>
                            <td>{{ number_format($listing->view_count) }}</td>
                            <td class="whitespace-nowrap">{{ $listing->updated_at->diffForHumans() }}</td>
                            <td>
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    @if ($listing->trashed())
                                        <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="restore">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Restaurar</button>
                                        </form>
                                    @else
                                        @if (! $listing->is_approved)
                                            <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">Aprovar</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="unapprove">
                                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Despublicar</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $listing->is_featured ? 'unfeature' : 'feature' }}">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">
                                                {{ $listing->is_featured ? 'Tirar destaque' : 'Destacar' }}
                                            </button>
                                        </form>

                                        @if ($listing->status !== \App\Models\MarketplaceListing::STATUS_HIDDEN)
                                            <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="hide">
                                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">Ocultar</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.marketplace.listings.action', ['id' => $listing->id]) }}"
                                              onsubmit="return confirm('Remover este anúncio?')">
                                            @csrf
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">Remover</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center opacity-60">Nenhum anúncio encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($listings->hasPages())
            <div class="admin-card-body border-t border-zinc-200 dark:border-zinc-800">{{ $listings->links() }}</div>
        @endif
    </div>
@endsection
