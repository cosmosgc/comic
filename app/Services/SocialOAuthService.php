<?php

namespace App\Services;

use App\Support\OAuthProviders;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Small OAuth 2.0 client shared by Google, Discord, GitHub and X/Twitter.
 *
 * It intentionally only covers the authorization-code flow and the public
 * profile fetch. No token is persisted: the app needs the identity once,
 * then relies on its own session.
 */
class SocialOAuthService
{
    /**
     * Build the provider consent URL with state and (when supported) PKCE.
     */
    public function authorizeUrl(string $providerName, string $state, string $verifier): string
    {
        $provider = $this->provider($providerName);

        $params = [
            'response_type' => 'code',
            'client_id' => (string) $provider['client_id'],
            'redirect_uri' => OAuthProviders::callbackUrl($providerName),
            'scope' => implode(' ', (array) ($provider['scopes'] ?? [])),
            'state' => $state,
        ];

        if ((bool) ($provider['pkce'] ?? false)) {
            $params['code_challenge'] = $this->challenge($verifier);
            $params['code_challenge_method'] = 'S256';
        }

        $authorizeUrl = (string) $provider['authorize_url'];
        $separator = str_contains($authorizeUrl, '?') ? '&' : '?';

        return $authorizeUrl.$separator.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the authorization code for an access token.
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(string $providerName, string $code, string $verifier): array
    {
        $provider = $this->provider($providerName);
        $clientId = (string) $provider['client_id'];
        $clientSecret = (string) $provider['client_secret'];

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => OAuthProviders::callbackUrl($providerName),
            'client_id' => $clientId,
        ];

        if ((bool) ($provider['pkce'] ?? false)) {
            $params['code_verifier'] = $verifier;
        }

        $request = Http::asForm()->acceptJson();

        if (($provider['token_auth'] ?? null) === 'basic') {
            $request = $request->withBasicAuth($clientId, $clientSecret);
        } else {
            $params['client_secret'] = $clientSecret;
        }

        $data = $request->post((string) $provider['token_url'], $params)->throw()->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new RuntimeException('The OAuth provider did not return an access token.');
        }

        return $data;
    }

    /**
     * Fetch and normalise the provider profile.
     *
     * @return array{id: string, name: string, nickname: string|null, email: string|null, email_verified: bool|null, avatar_url: string|null}
     */
    public function fetchProfile(string $providerName, string $accessToken): array
    {
        $provider = $this->provider($providerName);
        $map = (array) ($provider['map'] ?? []);

        $payload = $this->getJson((string) $provider['user_url'], $accessToken);

        if ($providerName === 'github') {
            $payload = $this->withGitHubEmail($payload, $provider, $accessToken);
        }

        $id = data_get($payload, $map['id'] ?? 'id');
        if (! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException('The OAuth provider did not return a user id.');
        }

        $nickname = $this->stringValue(data_get($payload, $map['nickname'] ?? null));
        $emailField = $map['email'] ?? null;
        $email = filled($emailField) ? $this->stringValue(data_get($payload, $emailField)) : null;
        $email = $email !== null ? mb_strtolower($email) : null;

        $name = $this->stringValue(data_get($payload, $map['name'] ?? null))
            ?? $nickname
            ?? $email
            ?? 'Usuário '.$providerName;

        $emailVerified = null;
        if (filled($map['email_verified'] ?? null)) {
            $emailVerified = (bool) data_get($payload, $map['email_verified']);
        }

        if ($providerName === 'github') {
            $emailVerified = $email !== null;
        }

        // X/Twitter's OAuth 2.0 profile has no e-mail field.
        if ($providerName === 'twitter') {
            $email = null;
            $emailVerified = null;
        }

        $avatar = $this->stringValue(data_get($payload, $map['avatar'] ?? null));
        if ($providerName === 'discord' && $avatar !== null) {
            $avatar = 'https://cdn.discordapp.com/avatars/'.$id.'/'.$avatar.'.png?size=256';
        }

        return [
            'id' => (string) $id,
            'name' => mb_substr($name, 0, 190),
            'nickname' => $nickname !== null ? mb_substr($nickname, 0, 190) : null,
            'email' => $email !== null ? mb_substr($email, 0, 190) : null,
            'email_verified' => $emailVerified,
            'avatar_url' => $avatar !== null ? mb_substr($avatar, 0, 500) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array<string, mixed>
     */
    protected function withGitHubEmail(array $payload, array $provider, string $accessToken): array
    {
        $emailUrl = $provider['email_url'] ?? null;

        if (filled($emailUrl)) {
            $emails = $this->getJson((string) $emailUrl, $accessToken);

            if (array_is_list($emails)) {
                $primary = collect($emails)->first(
                    fn (array $row) => ($row['primary'] ?? false) && ($row['verified'] ?? false)
                );
                $verified = collect($emails)->first(fn (array $row) => (bool) ($row['verified'] ?? false));
                $payload['email'] = $primary['email'] ?? $verified['email'] ?? null;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJson(string $url, string $accessToken): array
    {
        $data = Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'Comic Social Login'])
            ->get($url)
            ->throw()
            ->json();

        if (! is_array($data)) {
            throw new RuntimeException('The OAuth provider returned an unexpected payload.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function provider(string $name): array
    {
        $provider = OAuthProviders::get($name);

        if ($provider === null) {
            throw new RuntimeException('Unknown OAuth provider: '.$name);
        }

        return $provider;
    }

    protected function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
