<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class FollowFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guest_cannot_follow(): void
    {
        $user = User::factory()->create();

        $this->postJson("/users/{$user->id}/follow")->assertUnauthorized();
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_user_can_follow_and_unfollow(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)->postJson("/users/{$target->id}/follow")
            ->assertOk()
            ->assertJson(['following' => true, 'followers' => 1]);
        $this->assertDatabaseHas('follows', ['follower_id' => $actor->id, 'followed_id' => $target->id]);

        $this->actingAs($actor)->postJson("/users/{$target->id}/follow")
            ->assertOk()
            ->assertJson(['following' => false, 'followers' => 0]);
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_user_cannot_follow_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/users/{$user->id}/follow")->assertStatus(422);
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_following_feed_shows_only_followed_authors(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create(['name' => 'Followed Author']);
        $stranger = User::factory()->create(['name' => 'Stranger Author']);

        Post::factory()->create(['author_id' => $followed->id, 'text' => 'Post from followed']);
        Post::factory()->create(['author_id' => $stranger->id, 'text' => 'Post from stranger']);

        $viewer->following()->attach($followed->id);

        $this->actingAs($viewer)->get('/posts?tab=following')
            ->assertOk()
            ->assertSee('Post from followed')
            ->assertDontSee('Post from stranger');
    }

    public function test_following_tab_is_empty_without_follows(): void
    {
        $viewer = User::factory()->create();
        Post::factory()->create(['text' => 'Anyone post']);

        $this->actingAs($viewer)->get('/posts?tab=following')
            ->assertOk()
            ->assertDontSee('Anyone post');
    }

    public function test_follower_counts_are_exposed(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $a->following()->attach($b->id);

        $this->assertSame(1, $b->followers()->count());
        $this->assertSame(1, $a->following()->count());
        $this->assertTrue($a->isFollowing($b));
        $this->assertFalse($b->isFollowing($a));
    }

    public function test_hover_card_reports_follow_state(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create(['name' => 'Target User', 'handle' => 'targetuser']);

        $this->actingAs($viewer)
            ->getJson('/api/users/'.rawurlencode($target->name).'/card')
            ->assertOk()
            ->assertJson([
                'handle' => 'targetuser',
                'following' => false,
                'is_self' => false,
                'follows_enabled' => true,
            ]);
    }
}
