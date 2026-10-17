<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use App\Services\Gamification;
use App\Support\ActivityLogger;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_creating_a_comic_awards_the_most_experience(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);

        ActivityLogger::log('comic.uploaded', ['user' => $user, 'subject' => $comic]);

        // 100 for the comic + 5 for the first day of the streak.
        $this->assertSame(105, $user->fresh()->experience);
    }

    public function test_liking_a_comic_awards_less_experience(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $comic = Comic::factory()->create();

        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk();

        // 3 for the like + 5 first-day streak.
        $this->assertSame(8, $user->fresh()->experience);
    }

    public function test_re_liking_the_same_comic_does_not_award_experience_twice(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $comic = Comic::factory()->create();

        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk();
        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk(); // unlike
        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk(); // like again

        // Only the first ever like paid: 3 + 5 streak.
        $this->assertSame(8, $user->fresh()->experience);
    }

    public function test_posting_awards_experience(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['text' => 'Olá mundo'])->assertRedirect();

        // 25 for the post + 5 first-day streak.
        $this->assertSame(30, $user->fresh()->experience);
    }

    public function test_badges_are_awarded_at_their_threshold(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);

        ActivityLogger::log('comic.uploaded', ['user' => $user, 'subject' => $comic]);

        $user->refresh()->load('badges');
        $this->assertTrue($user->hasBadge('newcomer'));
        $this->assertTrue($user->hasBadge('first_comic'));
        $this->assertFalse($user->hasBadge('comic_master'));
    }

    public function test_level_up_happens_at_the_curve_threshold(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $comic = Comic::factory()->create(['user_id' => $user->id]);
            ActivityLogger::log('comic.uploaded', ['user' => $user, 'subject' => $comic]);
        }

        // 3 * 100 + 5 streak = 305; level 3 begins at 300.
        $this->assertSame(3, $user->fresh()->level);
    }

    public function test_guest_activity_awards_nothing(): void
    {
        ActivityLogger::log('search.comics', ['description' => 'anon']);

        $this->assertDatabaseCount('user_badges', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_with_remember_me_sets_a_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 1])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_me_leaves_the_token_empty(): void
    {
        $user = User::factory()->create(['remember_token' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_level_curve_is_triangular(): void
    {
        $this->assertSame(1, Gamification::levelFor(0));
        $this->assertSame(2, Gamification::levelFor(100));
        $this->assertSame(2, Gamification::levelFor(299));
        $this->assertSame(3, Gamification::levelFor(300));
        $this->assertSame(0, Gamification::thresholdFor(1));
        $this->assertSame(300, Gamification::thresholdFor(3));
    }

    public function test_progress_reports_the_next_level_gap(): void
    {
        $progress = Gamification::progress(User::factory()->create(['experience' => 150]));

        $this->assertSame(2, $progress['level']);
        $this->assertSame(50, $progress['into_level']);
        $this->assertSame(200, $progress['needed']);
        $this->assertSame(25, $progress['percent']);
    }

    public function test_profile_shows_level_and_badges(): void
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);
        ActivityLogger::log('comic.uploaded', ['user' => $user, 'subject' => $comic]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Nível')
            ->assertSee('XP')
            ->assertSee('Primeiro Comic');
    }
}
