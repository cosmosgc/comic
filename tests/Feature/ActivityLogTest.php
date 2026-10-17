<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Comic;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    protected function makeAdmin(): User
    {
        return User::factory()->create(['admin_level' => 1]);
    }

    public function test_liking_a_comic_records_activity_with_a_link(): void
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create();

        $this->actingAs($user)->postJson("/comics/{$comic->id}/like")->assertOk();

        $log = ActivityLog::where('action', 'comic.liked')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($comic->id, $log->subject_id);
        $this->assertSame(route('comics.showBySlug', $comic->slug), $log->url);
    }

    public function test_liking_a_post_records_activity_with_a_link(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->postJson("/posts/{$post->id}/like")->assertOk();

        $log = ActivityLog::where('action', 'post.liked')->firstOrFail();
        $this->assertSame($post->id, $log->subject_id);
        $this->assertSame(route('posts.show', $post->id), $log->url);
    }

    public function test_reading_a_comic_records_activity_once(): void
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['title' => 'Reading Target']);

        $this->actingAs($user)->get("/comics/id/{$comic->id}")->assertOk();
        // Re-opening by id or slug must not duplicate the entry.
        $this->get("/comics/{$comic->slug}")->assertOk();

        $logs = ActivityLog::where('action', 'comic.reading')->get();
        $this->assertCount(1, $logs);

        $log = $logs->first();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($comic->id, $log->subject_id);
        $this->assertStringContainsString('Reading Target', (string) $log->description);
        $this->assertSame(route('comics.showBySlug', $comic->slug), $log->url);
    }

    public function test_guests_do_not_record_reading_activity(): void
    {
        $comic = Comic::factory()->create();

        $this->get("/comics/id/{$comic->id}")->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['action' => 'comic.reading']);
    }

    public function test_previewing_a_scheduled_comic_does_not_record_reading(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create([
            'user_id' => $owner->id,
            'published_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)->get("/comics/id/{$comic->id}")->assertOk();

        $this->assertDatabaseMissing('activity_logs', ['action' => 'comic.reading']);
    }

    public function test_admin_activities_view_filters_the_reading_action(): void
    {
        $admin = $this->makeAdmin();
        $reader = User::factory()->create();
        $comic = Comic::factory()->create(['title' => 'Feed Reading']);

        $this->actingAs($reader)->get("/comics/{$comic->slug}")->assertOk();

        $this->actingAs($admin)->get('/admin/activities?action=comic.reading')
            ->assertOk()
            ->assertSee('Iniciou leitura')
            ->assertSee('Feed Reading');
    }

    public function test_login_event_records_activity(): void
    {
        // The listener is wired explicitly in AppServiceProvider.
        $this->assertTrue(
            app('events')->hasListeners(Login::class)
        );

        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_logout_records_activity_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_activities_view_attributes_guest_to_known_ip_user(): void
    {
        $admin = $this->makeAdmin();
        $known = User::factory()->create(['name' => 'Known User']);

        // A guest row shares the IP that the known user used before.
        ActivityLog::create([
            'user_id' => null,
            'action' => 'search.comics',
            'description' => 'Buscou comics por "naruto"',
            'ip_address' => '10.1.1.1',
        ]);
        ActivityLog::create([
            'user_id' => $known->id,
            'action' => 'auth.login',
            'description' => 'Fez login',
            'ip_address' => '10.1.1.1',
        ]);

        $this->actingAs($admin)->get('/admin/activities')
            ->assertOk()
            ->assertSee('Known User')
            ->assertSee('via IP')
            ->assertSee('https://www.whois.com/whois/10.1.1.1');
    }

    public function test_admin_activities_view_shows_visitante_for_unknown_ip(): void
    {
        $admin = $this->makeAdmin();

        ActivityLog::create([
            'user_id' => null,
            'action' => 'search.comics',
            'description' => 'Buscou comics por "ghost"',
            'ip_address' => '203.0.113.7',
        ]);

        $this->actingAs($admin)->get('/admin/activities')
            ->assertOk()
            ->assertSee('Visitante')
            ->assertDontSee('via IP');
    }

    public function test_non_admin_cannot_view_activities(): void
    {
        $this->get('/admin/activities')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/admin/activities')->assertRedirect('/');
    }

    public function test_admin_report_shows_stats_and_popular_searches(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'search.comics',
            'description' => 'Buscou comics por "naruto"',
            'metadata' => ['search' => 'naruto', 'results' => 3],
            'ip_address' => '10.0.0.5',
        ]);
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'search.comics',
            'description' => 'Buscou comics por "naruto"',
            'metadata' => ['search' => 'naruto', 'results' => 1],
            'ip_address' => '10.0.0.5',
        ]);

        $this->actingAs($admin)->get('/admin/activities/report')
            ->assertOk()
            ->assertSee('Atividade por dia')
            ->assertSee('Buscas mais populares')
            ->assertSee('naruto')
            ->assertSee('2×');
    }

    public function test_non_admin_cannot_view_activity_report(): void
    {
        $this->get('/admin/activities/report')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/admin/activities/report')->assertRedirect('/');
    }
}
