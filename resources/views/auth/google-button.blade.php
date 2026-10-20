@php($google = \App\Support\OAuthProviders::get('google'))

@if ($google !== null && filled($google['client_id'] ?? null) && filled($google['client_secret'] ?? null))
    <form method="POST"
          action="{{ route('oauth.google.credential') }}"
          data-google-credential-form
          data-intent="{{ $intent ?? 'login' }}"
          class="{{ $formClass ?? '' }}">
        @csrf
        <input type="hidden" name="credential" value="">
        <input type="hidden" name="intent" value="{{ $intent ?? 'login' }}">

        <span class="sr-only">{{ $action ?? 'Entrar' }} com Google</span>

        <div data-google-button
             data-client-id="{{ $google['client_id'] }}"
             data-width="{{ $width ?? 320 }}"
             data-text="{{ $text ?? 'signin_with' }}"
             class="flex justify-center"></div>

        <p data-google-error class="mt-1 hidden text-center text-xs text-rose-300" role="alert"></p>

        <noscript>
            <p class="mt-1 text-center text-xs text-amber-300">
                Ative o JavaScript para entrar com o Google.
            </p>
        </noscript>
    </form>
@endif
