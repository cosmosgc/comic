<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class ProfileLikesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        // Each test boots a fresh app (fresh :memory: database).
        $this->artisan('migrate');
    }

    protected function validProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Some Name',
            'email' => 'some@example.com',
        ], $overrides);
    }

    public function test_owner_sees_liked_posts_on_likes_tab(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['text' => 'A very liked post']);
        $post->likedByUsers()->attach($user->id);

        $response = $this->actingAs($user)->get('/profile?tab=likes');

        $response->assertStatus(200);
        $response->assertSee('A very liked post');
    }

    public function test_default_tab_hides_liked_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['text' => 'Hidden liked post']);
        $post->likedByUsers()->attach($user->id);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Liked posts'); // the tab itself
        $response->assertSee('Collections'); // the future tab slot
        $response->assertDontSee('Hidden liked post');
    }

    public function test_stranger_does_not_see_private_likes(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->create(['text' => 'Secretly liked post']);
        $post->likedByUsers()->attach($owner->id);

        $this->assertFalse((bool) $owner->show_liked_posts);

        // Forcing the tab falls back to comics when likes are private.
        $response = $this->actingAs($stranger)->get("/profile/{$owner->name}?tab=likes");

        $response->assertStatus(200);
        $response->assertDontSee('Posts liked by');
        $response->assertDontSee('Secretly liked post');
    }

    public function test_stranger_sees_likes_when_owner_opted_in(): void
    {
        $owner = User::factory()->create(['show_liked_posts' => true]);
        $stranger = User::factory()->create();
        $post = Post::factory()->create(['text' => 'Publicly liked post']);
        $post->likedByUsers()->attach($owner->id);

        $response = $this->actingAs($stranger)->get("/profile/{$owner->name}?tab=likes");

        $response->assertStatus(200);
        $response->assertSee('Posts liked by');
        $response->assertSee('Publicly liked post');
    }

    public function test_guest_sees_public_likes(): void
    {
        $owner = User::factory()->create(['show_liked_posts' => true]);
        $post = Post::factory()->create(['text' => 'Guest visible like']);
        $post->likedByUsers()->attach($owner->id);

        $response = $this->get("/profile/{$owner->name}?tab=likes");

        $response->assertStatus(200);
        $response->assertSee('Guest visible like');
    }

    public function test_owner_can_toggle_likes_visibility(): void
    {
        $user = User::factory()->create();
        $this->assertFalse((bool) $user->show_liked_posts);

        $this->actingAs($user)
            ->post('/profile/update', $this->validProfilePayload(['show_liked_posts' => '1']))
            ->assertRedirect(route('profile.show'));

        $this->assertTrue($user->fresh()->show_liked_posts);

        // Unchecked checkbox submits nothing → back to private.
        $this->actingAs($user)
            ->post('/profile/update', $this->validProfilePayload())
            ->assertRedirect(route('profile.show'));

        $this->assertFalse($user->fresh()->show_liked_posts);
    }
}
