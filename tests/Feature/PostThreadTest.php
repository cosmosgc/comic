<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class PostThreadTest extends TestCase
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

    protected function chain(int $depth): Post
    {
        $parent = null;
        for ($i = 0; $i < $depth; $i++) {
            $parent = Post::factory()->create([
                'text' => "Level {$i} post",
                'parent_id' => $parent?->id,
            ]);
        }

        return $parent;
    }

    public function test_reply_to_a_reply_attaches_to_that_node(): void
    {
        $user = User::factory()->create();
        $root = Post::factory()->create();
        $nested = Post::factory()->create(['parent_id' => $root->id]);

        $response = $this->actingAs($user)->post('/posts', [
            'text' => 'Nested answer',
            'parent_id' => $nested->id,
        ]);

        // Lands on the answered node's thread, where the reply is visible.
        $response->assertRedirect(route('posts.show', $nested));
        $this->assertDatabaseHas('posts', [
            'text' => 'Nested answer',
            'parent_id' => $nested->id,
        ]);
    }

    public function test_thread_renders_nested_chain(): void
    {
        $leaf = $this->chain(3); // root -> L1 -> L2(leaf)
        $root = $leaf->parent->parent;

        $response = $this->get("/posts/{$root->id}");

        $response->assertOk();
        $response->assertSee('Level 0 post');
        $response->assertSee('Level 1 post');
        $response->assertSee('Level 2 post');
    }

    public function test_deep_chain_collapses_into_continue_link(): void
    {
        $this->chain(5); // Level 0 (root) .. Level 4 (leaf)
        $root = Post::where('text', 'Level 0 post')->firstOrFail();
        $deepestVisible = Post::where('text', 'Level 3 post')->firstOrFail();
        $collapsed = Post::where('text', 'Level 4 post')->firstOrFail();

        $response = $this->get("/posts/{$root->id}");

        $response->assertOk();
        // Three levels render inline; deeper levels collapse.
        $response->assertSee('Level 0 post');
        $response->assertSee('Level 1 post');
        $response->assertSee('Level 2 post');
        $this->assertSame($deepestVisible->id, $collapsed->parent_id);
        $response->assertSee('Continue this thread');
        $response->assertSee(route('posts.show', $collapsed), false);
        $response->assertDontSee('Level 4 post');

        // The collapsed child's own thread shows the rest of the chain.
        $this->get("/posts/{$collapsed->id}")
            ->assertOk()
            ->assertSee('Level 4 post');
    }

    public function test_timestamp_links_to_thread(): void
    {
        $post = Post::factory()->create();

        $response = $this->get('/posts');

        $response->assertStatus(200);
        $response->assertSee(route('posts.show', $post), false);
    }

    public function test_whole_card_links_to_thread(): void
    {
        $post = Post::factory()->create();

        $response = $this->get('/posts');

        $response->assertStatus(200);
        $response->assertSee('data-thread-url="'.route('posts.show', $post).'"', false);
    }
}
