@extends('layouts.app')

@section('title', 'Entrando com '.$providerLabel)

@section('content')
<div class="flex min-h-[70vh] items-center justify-center py-10">
    <div class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 text-center shadow-xl backdrop-blur">
        <h1 class="text-xl font-bold">Entrando com {{ $providerLabel }}</h1>
        <p id="oauth-status" class="mt-2 text-sm text-zinc-400">Concluindo o login...</p>

        <form id="oauth-token-form" method="POST" action="{{ $postRoute }}" class="hidden">
            @csrf
            <input type="hidden" name="{{ $tokenField }}" value="">
            <input type="hidden" name="state" value="">
        </form>

        <a id="oauth-back"
           href="{{ $backRoute }}"
           class="mt-4 hidden text-sm font-semibold text-indigo-400 hover:text-indigo-300">
            Voltar para o login
        </a>

        <noscript>
            <p class="mt-4 text-sm text-amber-300">Ative o JavaScript para concluir o login.</p>
        </noscript>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const providerLabel = @json($providerLabel);
    const tokenField = @json($tokenField);
    const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
    const query = new URLSearchParams(window.location.search);
    const status = document.getElementById('oauth-status');
    const form = document.getElementById('oauth-token-form');
    const back = document.getElementById('oauth-back');

    const fail = function (message) {
        status.textContent = message;
        status.classList.remove('text-zinc-400');
        status.classList.add('text-rose-300');
        back.classList.remove('hidden');
        window.history.replaceState(null, '', window.location.pathname);
    };

    const error = hash.get('error') || query.get('error');

    if (error) {
        fail('O login com ' + providerLabel + ' foi cancelado.');
        return;
    }

    const token = hash.get(tokenField);
    const state = hash.get('state') || query.get('state');

    if (!token || !state) {
        fail('O ' + providerLabel + ' não devolveu as credenciais esperadas. Tente novamente.');
        return;
    }

    const tokenInput = form.querySelector('input[name="' + tokenField + '"]');

    if (!tokenInput) {
        fail('Não foi possível concluir o login. Tente novamente.');
        return;
    }

    tokenInput.value = token;
    form.querySelector('input[name="state"]').value = state;

    window.history.replaceState(null, '', window.location.pathname);
    form.submit();
})();
</script>
@endpush
