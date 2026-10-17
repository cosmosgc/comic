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

    public function test_level_curve_is_configurable(): void
    {
        config(['gamification.level_base' => 100, 'gamification.level_curve' => 'linear']);

        // Linear: L2 = 100, L3 = 200, L4 = 300.
        $this->assertSame(0, Gamification::thresholdFor(1));
        $this->assertSame(100, Gamification::thresholdFor(2));
        $this->assertSame(200, Gamification::thresholdFor(3));
        $this->assertSame(3, Gamification::levelFor(200));
        $this->assertSame(3, Gamification::levelFor(299));

        config(['gamification.level_curve' => 'quadratic']);
        // Quadratic: L2 = 100, L3 = 400.
        $this->assertSame(400, Gamification::thresholdFor(3));
    }

    public function test_recalculate_retrofits_existing_content_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        Comic::factory()->count(2)->create(['user_id' => $user->id]);

        // No activity log: the comic predates the feed.
        $this->assertSame(0, (int) $user->fresh()->experience);

        $result = Gamification::recalculate($user);
        $this->assertSame(200, $result['delta']);
        $this->assertSame(200, (int) $user->fresh()->experience);
        $this->assertSame(2, (int) $user->fresh()->level);

        // Running again adds nothing.
        $again = Gamification::recalculate($user->fresh());
        $this->assertSame(0, $again['delta']);
        $this->assertSame(200, (int) $user->fresh()->experience);
    }

    public function test_backfill_command_awards_pre_existing_content(): void
    {
        $user = User::factory()->create();
        Comic::factory()->create(['user_id' => $user->id]);

        $this->artisan('gamification:backfill')->assertSuccessful();

        $this->assertSame(100, (int) $user->fresh()->experience);
    }

    public function test_level_up_and_badge_unlock_send_notifications(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);

        ActivityLogger::log('comic.uploaded', ['user' => $user, 'subject' => $comic]);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        $this->assertGreaterThan(0, $user->fresh()->notifications()->count());
    }

    public function test_admin_can_grant_experience_and_a_badge(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $target = User::factory()->create(['experience' => 0, 'level' => 1]);

        $this->actingAs($admin)->post(route('admin.gamification.grant'), [
            'user_id' => $target->id,
            'exp' => 250,
            'badge' => 'explorer',
            'badge_action' => 'grant',
        ])->assertRedirect(route('admin.gamification'));

        $target->refresh()->load('badges');
        $this->assertSame(250, (int) $target->experience);
        $this->assertTrue($target->hasBadge('explorer'));
    }

    public function test_admin_settings_are_persisted_and_applied(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->post(route('admin.gamification.settings'), [
            'enabled' => '1',
            'level_base' => 50,
            'level_curve' => 'linear',
            'streak_bonus' => 7,
            'exp' => ['comic.uploaded' => 200],
        ])->assertRedirect(route('admin.gamification'));

        $this->assertDatabaseHas('gamification_settings', ['key' => 'level_base', 'value' => '50']);
        $this->assertSame(50, (int) config('gamification.level_base'));
        $this->assertSame('linear', config('gamification.level_curve'));
        $this->assertSame(200, (int) config('gamification.exp')['comic.uploaded']);
    }

    public function test_admin_can_view_gamification_panel(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->get(route('admin.gamification'))
            ->assertOk()
            ->assertSee('Gamificação')
            ->assertSee('Recalcular');
    }

    public function test_leaderboard_lists_users_by_experience(): void
    {
        $leader = User::factory()->create(['name' => 'Top Player', 'experience' => 900, 'level' => 5]);
        User::factory()->create(['name' => 'Rookie', 'experience' => 10, 'level' => 1]);

        $this->get('/leaderboard')
            ->assertOk()
            ->assertSee('Top Player')
            ->assertSee('Rookie');
    }

    public function test_profile_showcase_badges_are_saved(): void
    {
        $user = User::factory()->create();
        Gamification::grantBadge($user, 'newcomer');

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'showcase_badges' => ['newcomer'],
        ])->assertRedirect();

        $this->assertSame(['newcomer'], $user->fresh()->showcase_badges);
    }
}
