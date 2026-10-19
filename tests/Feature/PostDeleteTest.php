<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PostDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_owner_deletes_own_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->delete("/posts/{$post->id}")
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_delete_cascades_replies_and_quotes(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);
        $reply = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'parent_id' => $post->id,
        ]);
        $quote = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'referenced_post_id' => $post->id,
        ]);

        $this->actingAs($author)->delete("/posts/{$post->id}")->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('posts', ['id' => $reply->id]);
        $this->assertDatabaseMissing('posts', ['id' => $quote->id]);
    }

    public function test_stranger_and_guest_cannot_delete_post(): void
    {
        $author = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($stranger)->delete("/posts/{$post->id}")->assertForbidden();

        auth()->logout();
        $this->delete("/posts/{$post->id}")->assertRedirect('/login');

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_admin_can_delete_any_post(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['admin_level' => 1]);
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($admin)->delete("/posts/{$post->id}")->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_deleting_a_post_removes_its_media_files(): void
    {
        $author = User::factory()->create();
        $relative = 'storage/posts_media/post-delete-test.jpg';
        File::ensureDirectoryExists(public_path('storage/posts_media'));
        file_put_contents(public_path($relative), 'bytes');

        $post = Post::factory()->create([
            'author_id' => $author->id,
            'media' => [$relative],
        ]);

        $this->actingAs($author)->delete("/posts/{$post->id}")->assertRedirect();

        $this->assertFileDoesNotExist(public_path($relative));
    }

    public function test_editing_stamps_edited_at_and_view_count_does_not(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id, 'text' => 'Antes']);

        $this->actingAs($author)->put("/posts/{$post->id}", ['text' => 'Depois'])->assertRedirect();
        $this->assertNotNull($post->fresh()->edited_at);

        // Viewing bumps view_count (and updated_at) but must not touch
        // edited_at — that is why the badge no longer reads updated_at.
        $stamp = $post->fresh()->edited_at;
        $this->get("/posts/{$post->id}")->assertOk();
        $this->assertEquals($stamp, $post->fresh()->edited_at);

        $this->get("/posts/{$post->id}")->assertSee('editado');
    }
}
