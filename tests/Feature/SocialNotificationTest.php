<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class SocialNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_mention_creates_a_notification_for_the_mentioned_user(): void
    {
        $author = User::factory()->create(['name' => 'Author']);
        $mentioned = User::factory()->create(['name' => 'Bob', 'handle' => 'bob']);

        $this->actingAs($author)->post('/posts', ['text' => 'Hey @bob take a look'])->assertRedirect();

        $this->assertTrue(
            $mentioned->notifications()->where('data->kind', 'mention')->exists()
        );
    }

    public function test_reply_notifies_the_parent_author(): void
    {
        $parentAuthor = User::factory()->create(['name' => 'Parent']);
        $replier = User::factory()->create(['name' => 'Replier']);
        $parent = Post::factory()->create(['author_id' => $parentAuthor->id, 'text' => 'parent post']);

        $this->actingAs($replier)->post('/posts', [
            'text' => 'my reply',
            'parent_id' => $parent->id,
        ])->assertRedirect();

        $this->assertTrue(
            $parentAuthor->notifications()->where('data->kind', 'reply')->exists()
        );
    }

    public function test_quote_notifies_the_quoted_author(): void
    {
        $quoted = User::factory()->create(['name' => 'Quoted']);
        $quoter = User::factory()->create(['name' => 'Quoter']);
        $original = Post::factory()->create(['author_id' => $quoted->id]);

        $this->actingAs($quoter)->post('/posts', [
            'text' => 'quoting this',
            'referenced_post_id' => $original->id,
        ])->assertRedirect();

        $this->assertTrue(
            $quoted->notifications()->where('data->kind', 'quote')->exists()
        );
    }

    public function test_like_notifies_the_post_author_but_not_self(): void
    {
        $author = User::factory()->create();
        $liker = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($liker)->postJson("/posts/{$post->id}/like")->assertOk();

        $this->assertTrue($author->notifications()->where('data->kind', 'like')->exists());

        // Liking your own post never notifies you.
        $ownPost = Post::factory()->create(['author_id' => $liker->id]);
        $this->actingAs($liker)->postJson("/posts/{$ownPost->id}/like")->assertOk();
        $this->assertFalse($liker->notifications()->where('data->kind', 'like')->exists());
    }

    public function test_follow_creates_a_notification(): void
    {
        $actor = User::factory()->create(['name' => 'Follower']);
        $target = User::factory()->create();

        $this->actingAs($actor)->postJson("/users/{$target->id}/follow")->assertOk();

        $this->assertTrue($target->notifications()->where('data->kind', 'follow')->exists());
    }

    public function test_notification_feed_endpoint_returns_unread_count(): void
    {
        $author = User::factory()->create(['name' => 'Author']);
        $mentioned = User::factory()->create(['name' => 'Bob', 'handle' => 'bob']);

        $this->actingAs($author)->post('/posts', ['text' => 'hi @bob']);
        $author->notifications()->delete();

        $response = $this->actingAs($mentioned)->getJson('/notifications');

        $response->assertOk()->assertJson(['unread' => 1]);
    }
}
