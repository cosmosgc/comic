<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialOAuthService;
use App\Support\ActivityLogger;
use App\Support\OAuthProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * OAuth login and account linking. The same callback handles both cases:
 * an authenticated visitor is linking a provider to the current account,
 * a guest is signing in (or creating) a local account.
 */
class SocialAuthController extends Controller
{
    public function __construct(protected SocialOAuthService $oauth) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviders::isConfigured($provider), 404);

        $state = Str::random(40);
        $verifier = Str::random(64);

        $request->session()->put('oauth.pending', [
            'provider' => $provider,
            'state' => $state,
            'verifier' => $verifier,
        ]);

        return redirect()->away($this->oauth->authorizeUrl($provider, $state, $verifier));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviders::isConfigured($provider), 404);

        $pending = $request->session()->pull('oauth.pending');

        if (! is_array($pending)
            || ($pending['provider'] ?? null) !== $provider
            || ! hash_equals((string) ($pending['state'] ?? ''), (string) $request->query('state', ''))) {
            return $this->failure('A sessão de login social expirou. Tente novamente.');
        }

        if ($request->query('error') !== null) {
            return $this->failure('O login com '.OAuthProviders::label($provider).' foi cancelado.');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return $this->failure('O provedor não devolveu o código de autorização.');
        }

        try {
            $token = $this->oauth->exchangeCode($provider, $code, (string) ($pending['verifier'] ?? ''));
            $profile = $this->oauth->fetchProfile($provider, (string) $token['access_token']);
        } catch (Throwable $exception) {
            Log::warning('Social OAuth callback failed.', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('Não foi possível concluir o login social. Tente novamente.');
        }

        return $request->user() !== null
            ? $this->linkAccount($request, $provider, $profile)
            : $this->loginOrRegister($request, $provider, $profile);
    }

    /**
     * Google Identity Services posts the ID token here instead of using the
     * redirect callback, whose `iss=https://...` query parameter is blocked by
     * the host's web application firewall.
     */
    public function googleCredential(Request $request): RedirectResponse
    {
        abort_unless(OAuthProviders::isConfigured('google'), 404);

        $data = $request->validate([
            'credential' => ['required', 'string', 'max:8192'],
            'intent' => ['nullable', 'in:login,link'],
        ]);

        try {
            $profile = $this->oauth->verifyGoogleIdToken($data['credential']);
        } catch (Throwable $exception) {
            Log::warning('Google credential verification failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('Não foi possível validar a conta do Google. Tente novamente.');
        }

        if (($data['intent'] ?? 'login') === 'link') {
            abort_unless($request->user() !== null, 403);

            return $this->linkAccount($request, 'google', $profile);
        }

        return $request->user() !== null
            ? $this->linkAccount($request, 'google', $profile)
            : $this->loginOrRegister($request, 'google', $profile);
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        abort_unless(OAuthProviders::get($provider) !== null, 404);

        $deleted = $request->user()
            ->socialAccounts()
            ->where('provider', $provider)
            ->delete();

        return redirect()->to(route('profile.edit').'#contas')->with(
            $deleted ? 'success' : 'error',
            $deleted
                ? 'Conta do '.OAuthProviders::label($provider).' desconectada.'
                : 'Essa conta não estava conectada.'
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function linkAccount(Request $request, string $provider, array $profile): RedirectResponse
    {
        $user = $request->user();

        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $profile['id'])
            ->first();

        if ($existing !== null && ! $existing->user->is($user)) {
            return $this->failure('Essa conta do '.OAuthProviders::label($provider).' já está vinculada a outro usuário.');
        }

        SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            $this->accountAttributes($profile)
        );

        ActivityLogger::log('auth.social.linked', [
            'user' => $user,
            'subject' => $user,
            'description' => 'Vinculou a conta do '.OAuthProviders::label($provider),
            'metadata' => ['provider' => $provider],
            'ip' => $request->ip(),
        ]);

        return redirect()->to(route('profile.edit').'#contas')
            ->with('success', 'Conta do '.OAuthProviders::label($provider).' vinculada.');
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function loginOrRegister(Request $request, string $provider, array $profile): RedirectResponse
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $profile['id'])
            ->first();

        if ($account !== null) {
            return $this->authenticate($request, $account->user);
        }

        $email = $profile['email'] ?? null;

        // X/Twitter does not expose an e-mail through OAuth 2.0. A local
        // account is required first; the profile page can then link it.
        if (! is_string($email) || $email === '') {
            return $this->failure(
                'O '.OAuthProviders::label($provider).' não fornece um e-mail. Entre com e-mail e senha e vincule a conta em Editar perfil.'
            );
        }

        if (($profile['email_verified'] ?? false) !== true) {
            return $this->failure('O e-mail da conta do '.OAuthProviders::label($provider).' não está verificado.');
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $profile['name'],
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'handle' => Schema::hasColumn('users', 'handle')
                    ? User::generateUniqueHandle($profile['name'])
                    : null,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            if (Schema::hasColumn('collections', 'user_id')) {
                Collection::favoritesFor($user);
            }

            ActivityLogger::log('auth.register', [
                'user' => $user,
                'subject' => $user,
                'description' => 'Criou a conta "'.$user->name.'" via '.OAuthProviders::label($provider),
                'metadata' => ['provider' => $provider],
                'ip' => $request->ip(),
            ]);
        }

        // A verified provider e-mail also confirms a previously unverified
        // local address (e.g. an account created through the old form).
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            $this->accountAttributes($profile)
        );

        return $this->authenticate($request, $user);
    }

    protected function authenticate(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('comics.index'))
            ->with('success', 'Bem-vindo, '.$user->name.'!');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, string|null>
     */
    protected function accountAttributes(array $profile): array
    {
        return [
            'provider_user_id' => (string) $profile['id'],
            'nickname' => $profile['nickname'] ?? null,
            'name' => $profile['name'] ?? null,
            'email' => $profile['email'] ?? null,
            'avatar_url' => $profile['avatar_url'] ?? null,
        ];
    }

    protected function failure(string $message): RedirectResponse
    {
        $target = Auth::check() ? route('profile.edit').'#contas' : route('login');

        return redirect()->to($target)->with('error', $message);
    }
}
