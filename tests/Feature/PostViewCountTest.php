<?php

namespace Tests\Feature;

use App\Models\Post;
use Tests\TestCase;

class PostViewCountTest extends TestCase
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

    public function test_thread_view_counts_once_per_viewer(): void
    {
        $post = Post::factory()->create();
        // Factory instances don't carry DB defaults in memory; re-read.
        $this->assertSame(0, $post->fresh()->view_count);

        $this->get("/posts/{$post->id}")->assertOk();
        $this->assertSame(1, $post->fresh()->view_count);

        // Same viewer refreshing does not count again.
        $this->get("/posts/{$post->id}")->assertOk();
        $this->assertSame(1, $post->fresh()->view_count);
    }

    public function test_new_viewer_counts_again(): void
    {
        $post = Post::factory()->create();

        $this->get("/posts/{$post->id}")->assertOk();
        $this->assertSame(1, $post->fresh()->view_count);

        // Fresh session simulates another viewer.
        $this->app['session']->flush();

        $this->get("/posts/{$post->id}")->assertOk();
        $this->assertSame(2, $post->fresh()->view_count);
    }

    public function test_feed_includes_view_counts(): void
    {
        Post::factory()->create();

        $response = $this->getJson('/posts');

        $response->assertOk();
        $this->assertArrayHasKey(
            'view_count',
            $response->json('data.data.0'),
            'Feed rows must expose view_count for the eye icon.'
        );
    }
}
