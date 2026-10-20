@extends('layouts.admin-layout')

@section('title', 'Moderação do Mercado')
@section('page-title', 'Moderação do Mercado')

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="admin-alert admin-alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="space-y-4">
        @forelse ($reports as $report)
            <div class="admin-card">
                <div class="admin-card-header">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="admin-badge {{ $report->status === 'pending' ? 'admin-badge-warning' : 'admin-badge-secondary' }}">
                            {{ $report->status === 'pending' ? 'pendente' : ($report->status === 'reviewed' ? 'resolvida' : 'arquivada') }}
                        </span>
                        <span>{{ \App\Models\MarketplaceReport::reasonOptions()[$report->reason] ?? $report->reason }}</span>
                    </span>
                    <span class="text-xs opacity-60">{{ $report->created_at->format('d/m/Y H:i') }}</span>
                </div>

                <div class="admin-card-body space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide opacity-60">Anúncio</p>
                            @if ($report->listing)
                                <p class="font-medium">{{ $report->listing->title }}</p>
                                <p class="text-sm opacity-70">
                                    {{ $report->listing->seller?->name ?? '—' }} ·
                                    {{ $report->listing->category?->name ?? '—' }} ·
                                    {{ $report->listing->statusLabel() }}
                                    @if ($report->listing->trashed())
                                        · <span class="text-red-400">removido</span>
                                    @endif
                                </p>
                                <p class="mt-2">
                                    <a href="{{ route('admin.marketplace.listings', ['q' => $report->listing->title]) }}" class="text-xs underline">
                                        abrir na moderação de anúncios
                                    </a>
                                </p>
                            @else
                                <p class="opacity-60">Anúncio não encontrado.</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide opacity-60">Denunciante</p>
                            <p class="font-medium">{{ $report->reporter?->name ?? '—' }}</p>
                            <p class="text-sm opacity-70">{{ $report->reporter?->email }}</p>
                        </div>
                    </div>

                    @if ($report->details)
                        <div>
                            <p class="text-xs uppercase tracking-wide opacity-60">Detalhes</p>
                            <p class="whitespace-pre-line text-sm">{{ $report->details }}</p>
                        </div>
                    @endif

                    @if ($report->status === 'pending')
                        <form method="POST" action="{{ route('admin.marketplace.moderation.resolve', ['id' => $report->id]) }}"
                              class="flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                            @csrf
                            <label class="admin-check">
                                <input type="checkbox" name="hide_listing" value="1">
                                Ocultar o anúncio
                            </label>
                            <button type="submit" name="decision" value="reviewed" class="admin-btn admin-btn-sm admin-btn-primary">
                                Marcar como resolvida
                            </button>
                            <button type="submit" name="decision" value="dismissed" class="admin-btn admin-btn-sm admin-btn-secondary">
                                Arquivar sem ação
                            </button>
                        </form>
                    @else
                        <p class="border-t border-zinc-200 pt-4 text-xs opacity-60 dark:border-zinc-800">
                            Revisada por {{ $report->reviewer?->name ?? '—' }}
                            em {{ $report->reviewed_at?->format('d/m/Y H:i') ?? '—' }}.
                        </p>
                    @endif
                </div>
            </div>
        @empty
            <div class="admin-card">
                <div class="admin-card-body text-center opacity-60">Nenhuma denúncia no Mercado.</div>
            </div>
        @endforelse
    </div>

    @if ($reports->hasPages())
        <div class="mt-4">{{ $reports->links() }}</div>
    @endif
@endsection
