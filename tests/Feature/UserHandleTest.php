<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserHandleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_register_reserves_a_unique_handle(): void
    {
        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('janedoe', $user->handle);
    }

    public function test_generate_unique_handle_suffixes_collisions(): void
    {
        User::factory()->create(['name' => 'Same Name', 'handle' => 'samename']);
        User::factory()->create(['name' => 'Same Name', 'handle' => 'samename1']);

        $this->assertSame('samename2', User::generateUniqueHandle('Same Name'));
    }

    public function test_public_profile_is_reachable_by_handle(): void
    {
        $user = User::factory()->create(['name' => 'Actual Name', 'handle' => 'coolhandle']);

        $this->get('/profile/coolhandle')->assertOk()->assertSee('Actual Name');
    }

    public function test_mention_token_resolves_by_handle(): void
    {
        $user = User::factory()->create(['name' => 'Some One', 'handle' => 'somehandle']);

        $this->assertTrue(User::findByMentionToken('somehandle')->is($user));
        // Legacy slug-style mention still resolves.
        $this->assertTrue(User::findByMentionToken('someone')->is($user));
    }

    public function test_handle_name_falls_back_to_name_slug(): void
    {
        $user = User::factory()->create(['name' => 'Fallback User', 'handle' => null]);

        $this->assertSame('fallbackuser', $user->handleName());
    }
}
