<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Comment;
use App\Models\User;
use Tests\TestCase;

class ComicEngagementTest extends TestCase
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

    protected function makeComic(?User $owner = null): Comic
    {
        return Comic::create([
            'title' => 'Test Comic '.uniqid(),
            'slug' => 'test-comic-'.uniqid(),
            'user_id' => ($owner ?? User::factory()->create())->id,
        ]);
    }

    // -- comments --------------------------------------------------------

    public function test_guest_cannot_comment(): void
    {
        $comic = $this->makeComic();

        $this->postJson("/comics/{$comic->id}/comments", ['body' => 'Hi'])
            ->assertUnauthorized();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_body_is_validated(): void
    {
        $user = User::factory()->create();
        $comic = $this->makeComic();

        $this->actingAs($user)->postJson("/comics/{$comic->id}/comments", ['body' => ''])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson("/comics/{$comic->id}/comments", ['body' => str_repeat('x', 2001)])
            ->assertUnprocessable();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_user_can_post_comment(): void
    {
        $user = User::factory()->create();
        $comic = $this->makeComic();

        $response = $this->actingAs($user)->postJson("/comics/{$comic->id}/comments", [
            'body' => 'Great chapter!',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('comments', [
            'body' => 'Great chapter!',
            'comic_id' => $comic->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_comments_index_is_paginated_newest_first(): void
    {
        $user = User::factory()->create();
        $comic = $this->makeComic();
        Comment::factory()->count(12)->create([
            'comic_id' => $comic->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/comics/{$comic->id}/comments");

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertSame(12, $response->json('total'));
        $this->assertArrayHasKey('user', $response->json('data.0'));
    }

    public function test_author_can_edit_own_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/comments/{$comment->id}", [
            'body' => 'Edited text',
        ]);

        $response->assertOk();
        $this->assertSame('Edited text', $comment->fresh()->body);
    }

    public function test_stranger_cannot_edit_comment(): void
    {
        $comment = Comment::factory()->create(['body' => 'Original']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson("/comments/{$comment->id}", [
            'body' => 'Hijacked',
        ])->assertForbidden();
        $this->assertSame('Original', $comment->fresh()->body);
    }

    public function test_admin_can_edit_any_comment(): void
    {
        $comment = Comment::factory()->create(['body' => 'Original']);
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->putJson("/comments/{$comment->id}", [
            'body' => 'Moderated',
        ])->assertOk();
        $this->assertSame('Moderated', $comment->fresh()->body);
    }

    public function test_author_can_delete_own_comment_but_stranger_cannot(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/comments/{$comment->id}")->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);

        $this->actingAs($user)->deleteJson("/comments/{$comment->id}")->assertOk();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_comments_cascade_when_comic_deleted(): void
    {
        $comic = $this->makeComic();
        Comment::factory()->create(['comic_id' => $comic->id]);

        $comic->delete();

        $this->assertDatabaseCount('comments', 0);
    }

    // -- comic likes -----------------------------------------------------

    public function test_guest_cannot_like_comic(): void
    {
        $comic = $this->makeComic();

        $this->postJson("/comics/{$comic->id}/like")->assertUnauthorized();
        $this->assertDatabaseCount('comic_user_likes', 0);
    }

    public function test_user_can_like_and_unlike_comic(): void
    {
        $user = User::factory()->create();
        $comic = $this->makeComic();

        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")
            ->assertOk()->assertJson(['liked' => true, 'count' => 1]);

        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")
            ->assertOk()->assertJson(['liked' => false, 'count' => 0]);

        // Toggling never creates duplicates.
        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk();
        $this->assertSame(1, $comic->likedByUsers()->count());
    }
}
