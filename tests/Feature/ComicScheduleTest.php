<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComicScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_scheduled_comic_is_a_locked_teaser_on_index_and_hidden_from_search(): void
    {
        Comic::factory()->create(['title' => 'Visible Now XYZ', 'published_at' => null]);
        Comic::factory()->create(['title' => 'Future Hidden XYZ', 'published_at' => now()->addDay()]);

        // Guests get no early access: the home card is a blurred teaser that
        // points to login, while search does not expose scheduled comics.
        $this->get('/')->assertOk()
            ->assertSee('Visible Now XYZ')
            ->assertSee('Future Hidden XYZ')
            ->assertSee('blur-sm', false)
            ->assertSee(route('login'), false)
            ->assertSee('Entre para ler');

        $this->get('/comics/search?search=XYZ')->assertOk()
            ->assertSee('Visible Now XYZ')
            ->assertDontSee('Future Hidden XYZ');
    }

    public function test_calendar_lists_scheduled_comics_inside_the_public_preview_window(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        Comic::factory()->create([
            'title' => 'Calendário Visível XYZ',
            'published_at' => '2026-10-23 09:00:00',
        ]);

        $this->get(route('comics.calendar'))
            ->assertOk()
            ->assertSee('Calendário de lançamentos')
            ->assertSee('Calendário Visível XYZ')
            ->assertSee('23/10/2026 · 09:00');
    }

    public function test_calendar_obscures_far_ahead_scheduled_comics(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        Comic::factory()->create([
            'title' => 'Spoiler Distante XYZ',
            'published_at' => now()->addDays(Comic::SCHEDULE_PREVIEW_DAYS + 1),
        ]);

        $this->get(route('comics.calendar'))
            ->assertOk()
            ->assertSee('Comic em breve')
            ->assertSee('blur-sm', false)
            ->assertDontSee('Spoiler Distante XYZ');
    }

    public function test_homepage_renders_calendar_links_in_navigation_and_listing_filters(): void
    {
        $calendarUrl = route('comics.calendar');

        $this->get('/')
            ->assertSee('href="'.$calendarUrl.'"', false)
            ->assertSeeText('Calendário')
            ->assertSeeText('Lançamentos');
    }

    public function test_guest_gets_404_on_scheduled_reader(): void
    {
        $comic = Comic::factory()->create(['published_at' => now()->addDay()]);

        $this->get("/comics/id/{$comic->id}")->assertNotFound();
        $this->get("/comics/{$comic->slug}")->assertNotFound();
    }

    public function test_stranger_gets_404_but_owner_and_admin_can_preview(): void
    {
        $owner = User::factory()->create();
        // Beyond the members' early access window: signed-in strangers have
        // no business here, only the owner and admins preview the queue.
        $comic = Comic::factory()->create(['user_id' => $owner->id, 'published_at' => now()->addDays(30)]);

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get("/comics/id/{$comic->id}")->assertNotFound();

        $this->actingAs($owner)->get("/comics/id/{$comic->id}")
            ->assertOk()->assertSee('Agendado', false);

        $this->actingAs(User::factory()->create(['admin_level' => 1]))
            ->get("/comics/id/{$comic->id}")->assertOk();
    }

    public function test_past_published_at_is_public(): void
    {
        $comic = Comic::factory()->create(['published_at' => now()->subHour()]);

        $this->assertTrue($comic->isPublished());
        $this->get("/comics/id/{$comic->id}")->assertOk();
    }

    public function test_schedule_calendar_groups_future_comics_by_date_and_excludes_the_edited_comic(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $owner = User::factory()->create();
        Comic::factory()->count(2)->create([
            'published_at' => '2026-10-21 09:00:00',
        ]);
        $comic = Comic::factory()->create([
            'user_id' => $owner->id,
            'published_at' => '2026-10-22 09:00:00',
        ]);
        Comic::factory()->create([
            'published_at' => '2026-10-19 09:00:00',
        ]);

        $this->assertSame([
            '2026-10-21' => 2,
            '2026-10-22' => 1,
        ], Comic::scheduledDateCounts());

        $this->actingAs($owner)
            ->get(route('comics.create'))
            ->assertOk()
            ->assertSee("data-scheduled-dates='{\"2026-10-21\":2,\"2026-10-22\":1}'", false);

        $this->actingAs($owner)
            ->get(route('comics.edit', $comic))
            ->assertOk()
            ->assertSee("data-scheduled-dates='{\"2026-10-21\":2}'", false);
    }

    public function test_owner_can_schedule_on_update_and_clear_to_publish(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id, 'published_at' => null]);
        // Out of the members' early access window, so the stranger check
        // below keeps its meaning.
        $future = now()->addDays(30)->format('Y-m-d\TH:i');

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'published_at' => $future])
            ->assertRedirect();

        $this->assertTrue($comic->fresh()->isScheduled());
        // actingAs persists across requests in the test, so check as a stranger.
        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get("/comics/id/{$comic->id}")->assertNotFound();
        $this->actingAs($owner)->get("/comics/{$comic->id}/edit")
            ->assertOk()->assertSee('Agendado', false);

        // Clearing the field publishes immediately.
        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'published_at' => ''])
            ->assertRedirect();

        $this->assertTrue($comic->fresh()->isPublished());
        $this->get("/comics/id/{$comic->id}")->assertOk();
    }

    public function test_owner_can_schedule_on_create(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $response = $this->postJson('/comics', [
            'title' => 'Scheduled Upload '.uniqid(),
            'images' => [UploadedFile::fake()->image('page01.jpg')->size(100)],
            'published_at' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertOk();
        $comicId = $response->json('comic_id');
        $this->assertNotEmpty($comicId);

        try {
            $comic = Comic::findOrFail($comicId);
            $this->assertTrue($comic->isScheduled());
            // actingAs persists across requests in the test: verify as guest.
            Auth::logout();
            $this->get("/comics/id/{$comicId}")->assertNotFound();
        } finally {
            $this->deleteComicTree((int) $comicId);
        }
    }

    public function test_invalid_published_at_is_rejected(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'published_at' => 'not-a-date'])
            ->assertSessionHasErrors('published_at');
    }

    public function test_public_profile_hides_scheduled_from_visitors(): void
    {
        $owner = User::factory()->create();
        Comic::factory()->create(['user_id' => $owner->id, 'title' => 'Profile Visible ABC']);
        Comic::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Profile Future ABC',
            'published_at' => now()->addDay(),
        ]);

        $this->get("/profile/{$owner->name}")->assertOk()
            ->assertSee('Profile Visible ABC')
            ->assertDontSee('Profile Future ABC');

        // The owner still sees their own scheduled comic on their profile.
        $this->actingAs($owner)->get("/profile/{$owner->name}")->assertOk()
            ->assertSee('Profile Future ABC');
    }

    public function test_admin_schedules_a_comic_from_the_admin_list(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Admin Scheduled One',
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->post("/admin/comics/{$comic->id}/schedule", [
                'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.comics'))
            ->assertSessionHas('success');

        $this->assertTrue($comic->fresh()->isScheduled());

        // The public index shows a locked teaser, while the admin list can
        // still identify and manage the scheduled comic.
        Auth::logout();
        $this->get('/')->assertOk()
            ->assertSee('Admin Scheduled One')
            ->assertSee('Entre para ler');

        $this->actingAs($admin)->get('/admin/comics')->assertOk()
            ->assertSee('Admin Scheduled One')
            ->assertSee('Em breve');
    }

    public function test_admin_publishes_a_scheduled_comic_right_away(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Admin Publish Now',
            'published_at' => now()->addDays(3),
        ]);

        $this->actingAs($admin)
            ->post("/admin/comics/{$comic->id}/schedule", ['published_at' => ''])
            ->assertRedirect(route('admin.comics'))
            ->assertSessionHas('success');

        $comic->refresh();
        $this->assertNull($comic->published_at);
        $this->assertTrue($comic->isPublished());

        $this->get('/')->assertOk()->assertSee('Admin Publish Now');
        $this->get('/admin/comics')->assertOk()->assertSee('Publicado');
    }

    public function test_guest_and_non_admin_cannot_schedule(): void
    {
        $comic = Comic::factory()->create(['published_at' => null]);
        $when = ['published_at' => now()->addDay()->format('Y-m-d\TH:i')];

        $this->post("/admin/comics/{$comic->id}/schedule", $when)->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->post("/admin/comics/{$comic->id}/schedule", $when)->assertRedirect('/');

        $this->assertNull($comic->fresh()->published_at);
    }

    public function test_invalid_schedule_date_is_rejected(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id, 'published_at' => null]);

        $this->actingAs($admin)
            ->post("/admin/comics/{$comic->id}/schedule", ['published_at' => 'not-a-date'])
            ->assertSessionHasErrors('published_at');

        $this->assertNull($comic->fresh()->published_at);
    }

    protected function deleteComicTree(int $comicId): void
    {
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
