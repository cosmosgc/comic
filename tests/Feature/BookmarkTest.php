<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guest_cannot_bookmark(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/posts/{$post->id}/bookmark")->assertUnauthorized();
        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_user_can_toggle_bookmark(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->postJson("/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJson(['bookmarked' => true]);
        $this->assertDatabaseHas('bookmarks', ['user_id' => $user->id, 'post_id' => $post->id]);

        $this->actingAs($user)->postJson("/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJson(['bookmarked' => false]);
        $this->assertDatabaseCount('bookmarks', 0);
    }

    public function test_bookmarks_page_is_private_and_lists_saved_posts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $saved = Post::factory()->create(['text' => 'Saved secret post']);
        $notSaved = Post::factory()->create(['text' => 'Not saved post']);

        $owner->bookmarkedPosts()->attach($saved->id);

        $this->actingAs($owner)->get('/bookmarks')
            ->assertOk()
            ->assertSee('Saved secret post')
            ->assertDontSee('Not saved post');

        // Someone else never sees the owner's saved post.
        $this->actingAs($other)->get('/bookmarks')
            ->assertOk()
            ->assertDontSee('Saved secret post');
    }

    public function test_expired_guest_is_redirected_from_bookmarks(): void
    {
        $this->get('/bookmarks')->assertRedirect('/login');
    }
}
