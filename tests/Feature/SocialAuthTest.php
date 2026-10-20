<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);

        $this->artisan('migrate');

        $this->configureProvider('google', [
            'client_id' => 'google-id',
            'client_secret' => 'google-secret',
            'redirect' => 'http://localhost/auth/google/callback',
            'authorize_url' => 'https://oauth.test/google/authorize',
            'token_url' => 'https://oauth.test/google/token',
            'user_url' => 'https://oauth.test/google/user',
        ]);
    }

    public function test_redirect_builds_the_provider_url_and_remembers_state(): void
    {
        $response = $this->get(route('oauth.redirect', ['provider' => 'google']));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://oauth.test/google/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $pending = session('oauth.pending');

        $this->assertSame('google-id', $query['client_id']);
        $this->assertSame('http://localhost/auth/google/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame($pending['state'], $query['state']);
        $this->assertNotEmpty($query['code_challenge']);
        $this->assertSame('S256', $query['code_challenge_method']);
    }

    public function test_google_callback_creates_a_verified_account_and_logs_in(): void
    {
        Http::fake([
            'https://oauth.test/google/token' => Http::response(['access_token' => 'access-token']),
            'https://oauth.test/google/user' => Http::response([
                'sub' => 'google-123',
                'name' => 'Jane Doe',
                'given_name' => 'jane',
                'email' => 'jane@example.com',
                'email_verified' => true,
                'picture' => 'https://example.com/jane.png',
            ]),
        ]);

        $this->withSession([
            'oauth.pending' => [
                'provider' => 'google',
                'state' => 'state-123',
                'verifier' => 'verifier-123',
            ],
        ])->get(route('oauth.callback', [
            'provider' => 'google',
            'state' => 'state-123',
            'code' => 'code-123',
        ]))->assertRedirect();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
    }

    public function test_guest_with_matching_verified_email_links_the_provider_to_the_existing_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'jane@example.com']);

        Http::fake([
            'https://oauth.test/google/token' => Http::response(['access_token' => 'access-token']),
            'https://oauth.test/google/user' => Http::response([
                'sub' => 'google-123',
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'email_verified' => true,
            ]),
        ]);

        $this->withSession([
            'oauth.pending' => [
                'provider' => 'google',
                'state' => 'state-123',
                'verifier' => 'verifier-123',
            ],
        ])->get(route('oauth.callback', [
            'provider' => 'google',
            'state' => 'state-123',
            'code' => 'code-123',
        ]))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'google-123',
        ]);
    }

    public function test_authenticated_user_can_link_and_unlink_a_provider(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://oauth.test/google/token' => Http::response(['access_token' => 'access-token']),
            'https://oauth.test/google/user' => Http::response([
                'sub' => 'google-123',
                'name' => 'Jane Doe',
                'email' => 'other@example.com',
                'email_verified' => true,
            ]),
        ]);

        $this->actingAs($user)
            ->withSession([
                'oauth.pending' => [
                    'provider' => 'google',
                    'state' => 'state-123',
                    'verifier' => 'verifier-123',
                ],
            ])
            ->get(route('oauth.callback', [
                'provider' => 'google',
                'state' => 'state-123',
                'code' => 'code-123',
            ]))
            ->assertRedirect(route('profile.edit').'#contas');

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'google-123',
        ]);

        $this->post(route('oauth.unlink', ['provider' => 'google']))
            ->assertRedirect(route('profile.edit').'#contas');

        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    public function test_invalid_state_does_not_authenticate_or_touch_the_provider(): void
    {
        Http::fake();

        $this->withSession([
            'oauth.pending' => [
                'provider' => 'google',
                'state' => 'expected-state',
                'verifier' => 'verifier-123',
            ],
        ])->get(route('oauth.callback', [
            'provider' => 'google',
            'state' => 'wrong-state',
            'code' => 'code-123',
        ]))->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_twitter_without_email_asks_the_user_to_link_from_the_profile(): void
    {
        $this->configureProvider('twitter', [
            'client_id' => 'twitter-id',
            'client_secret' => 'twitter-secret',
            'redirect' => 'http://localhost/auth/twitter/callback',
            'authorize_url' => 'https://oauth.test/twitter/authorize',
            'token_url' => 'https://oauth.test/twitter/token',
            'user_url' => 'https://oauth.test/twitter/user',
        ]);

        Http::fake([
            'https://oauth.test/twitter/token' => Http::response(['access_token' => 'access-token']),
            'https://oauth.test/twitter/user' => Http::response([
                'data' => [
                    'id' => 'twitter-123',
                    'name' => 'Jane',
                    'username' => 'jane',
                ],
            ]),
        ]);

        $this->withSession([
            'oauth.pending' => [
                'provider' => 'twitter',
                'state' => 'state-123',
                'verifier' => 'verifier-123',
            ],
        ])->get(route('oauth.callback', [
            'provider' => 'twitter',
            'state' => 'state-123',
            'code' => 'code-123',
        ]))->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_login_page_only_shows_providers_with_credentials(): void
    {
        $this->configureProvider('discord', [
            'client_id' => 'discord-id',
            'client_secret' => 'discord-secret',
            'redirect' => 'http://localhost/auth/discord/callback',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Entrar com Google')
            ->assertSee('Entrar com Discord')
            ->assertDontSee('Entrar com GitHub');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function configureProvider(string $provider, array $overrides): void
    {
        $current = config('oauth.providers.'.$provider, []);

        config(['oauth.providers.'.$provider => array_merge($current, $overrides)]);
    }
}
