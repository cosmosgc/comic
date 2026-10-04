<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class PostLikeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        // RefreshDatabase would wipe the configured (dev) database, so
        // migrate the in-memory sqlite connection explicitly instead.
        // Each test boots a fresh app (fresh :memory: database).
        $this->artisan('migrate');
    }

    public function test_guest_cannot_like(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/posts/{$post->id}/like")->assertUnauthorized();
        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_user_can_like_and_unlike(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $like = $this->actingAs($user)->postJson("/posts/{$post->id}/like");

        $like->assertOk()->assertJson(['liked' => true, 'count' => 1]);
        $this->assertDatabaseHas('post_likes', ['user_id' => $user->id, 'post_id' => $post->id]);

        $unlike = $this->actingAs($user)->postJson("/posts/{$post->id}/like");

        $unlike->assertOk()->assertJson(['liked' => false, 'count' => 0]);
        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_duplicate_like_is_impossible(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->postJson("/posts/{$post->id}/like")->assertOk();
        // Toggling twice returns to unliked — count never exceeds one.
        $this->actingAs($user)->postJson("/posts/{$post->id}/like")->assertOk();
        $this->actingAs($user)->postJson("/posts/{$post->id}/like")->assertOk();

        $this->assertSame(1, $post->likedByUsers()->count());
    }

    public function test_feed_includes_like_counts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $post->likedByUsers()->attach($user->id);

        $response = $this->actingAs($user)->getJson('/posts');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.data.0.liked_by_users_count'));
    }
}
