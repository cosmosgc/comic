@extends('layouts.admin-layout')

@section('title', 'Mercado')
@section('page-title', 'Mercado')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
        @foreach ([
            'Ativos' => $totals['active'],
            'Aguardando' => $totals['pending'],
            'Rascunhos' => $totals['draft'],
            'Vendidos' => $totals['sold'],
            'Expirados' => $totals['expired'],
            'Denúncias' => $totals['reports'],
            'Categorias' => $totals['categories'],
        ] as $label => $value)
            <div class="admin-card">
                <div class="admin-card-body">
                    <p class="text-2xl font-bold">{{ number_format($value) }}</p>
                    <p class="text-xs opacity-70">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($reports->isNotEmpty())
        <div class="mt-6 admin-card">
            <div class="admin-card-header">
                <span>Denúncias pendentes</span>
                <a href="{{ route('admin.marketplace.moderation') }}" class="text-xs underline">ver fila</a>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table-compact">
                    <thead>
                        <tr><th>Anúncio</th><th>Motivo</th><th>Denunciante</th><th>Quando</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr>
                                <td>
                                    @if ($report->listing)
                                        <a href="{{ route('admin.marketplace.listings', ['q' => $report->listing->title]) }}" class="hover:underline">
                                            {{ $report->listing->title }}
                                        </a>
                                    @else
                                        <span class="opacity-60">Anúncio removido</span>
                                    @endif
                                </td>
                                <td>{{ \App\Models\MarketplaceReport::reasonOptions()[$report->reason] ?? $report->reason }}</td>
                                <td>{{ $report->reporter?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $report->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-6 admin-card">
        <div class="admin-card-header">
            <span>Últimos anúncios</span>
            <a href="{{ route('admin.marketplace.listings') }}" class="text-xs underline">ver todos</a>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-compact">
                <thead>
                    <tr><th>Anúncio</th><th>Vendedor</th><th>Categoria</th><th>Estado</th><th>Preço</th><th>Atualizado</th></tr>
                </thead>
                <tbody>
                    @forelse ($latest as $listing)
                        <tr>
                            <td>
                                <a href="{{ route('marketplace.show', ['listing' => $listing->slug]) }}" target="_blank" rel="noopener" class="hover:underline">
                                    {{ $listing->title }}
                                </a>
                                @if ($listing->trashed())
                                    <span class="admin-badge admin-badge-danger ml-1">removido</span>
                                @endif
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
                            <td class="whitespace-nowrap">{{ $listing->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center opacity-60">Nenhum anúncio ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.marketplace.categories') }}" class="admin-btn admin-btn-primary">Categorias</a>
        <a href="{{ route('admin.marketplace.listings') }}" class="admin-btn admin-btn-secondary">Anúncios</a>
        <a href="{{ route('admin.marketplace.moderation') }}" class="admin-btn admin-btn-secondary">Moderação</a>
        <a href="{{ route('admin.marketplace.settings') }}" class="admin-btn admin-btn-secondary">Configurações</a>
        <form method="POST" action="{{ route('admin.marketplace.expire') }}">
            @csrf
            <button type="submit" class="admin-btn admin-btn-secondary">Rodar expiração agora</button>
        </form>
    </div>
@endsection
