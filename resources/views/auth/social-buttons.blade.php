@php($providers = \App\Support\OAuthProviders::configured())
@if ($providers !== [])
    <div class="mt-6 space-y-2">
        <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-zinc-500">
            <span class="h-px flex-1 bg-zinc-800"></span>
            <span>ou {{ $action ?? 'continuar' }} com</span>
            <span class="h-px flex-1 bg-zinc-800"></span>
        </div>

        @foreach ($providers as $providerKey => $provider)
            <a href="{{ route('oauth.redirect', ['provider' => $providerKey]) }}"
               class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition {{ $provider['button'] ?? 'border border-zinc-700 bg-zinc-900 text-white hover:bg-zinc-800' }}">
                <span aria-hidden="true">{{ $provider['icon'] ?? '🔗' }}</span>
                {{ $action ?? 'Entrar' }} com {{ $provider['label'] }}
            </a>
        @endforeach
    </div>
@endif
