<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class PostReplyTest extends TestCase
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

    public function test_authenticated_user_can_reply(): void
    {
        $user = User::factory()->create();
        $parent = Post::factory()->create();

        $response = $this->actingAs($user)->post('/posts', [
            'text' => 'A threaded reply',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect(route('posts.show', $parent));
        $this->assertDatabaseHas('posts', [
            'text' => 'A threaded reply',
            'parent_id' => $parent->id,
            'author_id' => $user->id,
        ]);
        $this->assertSame(1, $parent->replies()->count());
    }

    public function test_reply_requires_existing_parent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/posts', [
            'text' => 'Reply to nothing',
            'parent_id' => 999999,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_guest_cannot_reply(): void
    {
        $parent = Post::factory()->create();

        $response = $this->post('/posts', [
            'text' => 'Ghost reply',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_thread_page_shows_parent_and_replies(): void
    {
        $grandparent = Post::factory()->create(['text' => 'Root post']);
        $parent = Post::factory()->create(['text' => 'Middle post', 'parent_id' => $grandparent->id]);
        Post::factory()->create(['text' => 'First reply', 'parent_id' => $parent->id]);
        Post::factory()->create(['text' => 'Second reply', 'parent_id' => $parent->id]);

        $response = $this->get("/posts/{$parent->id}");

        $response->assertOk();
        $response->assertSee('Middle post');
        $response->assertSee('Root post');
        $response->assertSee('First reply');
        $response->assertSee('Second reply');
    }

    public function test_thread_page_is_public(): void
    {
        $post = Post::factory()->create();

        $this->get("/posts/{$post->id}")->assertOk();
    }

    public function test_feed_includes_reply_counts(): void
    {
        $parent = Post::factory()->create();
        Post::factory()->create(['parent_id' => $parent->id]);
        Post::factory()->create(['parent_id' => $parent->id]);

        $response = $this->getJson('/posts');

        $response->assertOk();
        $replyCounts = collect($response->json('data.data'))->pluck('replies_count', 'id');
        $this->assertSame(2, $replyCounts[$parent->id]);
    }
}
