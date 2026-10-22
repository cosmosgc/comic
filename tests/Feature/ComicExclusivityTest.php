<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use Tests\TestCase;

/**
 * Members-only comics and the members' early access window, plus the two
 * ways the flag gets set (owner edit form, admin panel toggle).
 *
 * Visibility matrix:
 *  - guest:  released comics only; exclusive ones stay in the lists as
 *            locked teasers, but the reader and the pages API answer 404;
 *  - member: everything released, every exclusive, and scheduled comics
 *            whose release falls inside the next week;
 *  - owner:  their own whole queue; admin: every queue.
 */
class ComicExclusivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_member_reads_an_exclusive_comic_that_a_guest_cannot_reach(): void
    {
        $comic = Comic::factory()->create(['title' => 'Only Members XYZ', 'is_exclusive' => true]);

        $this->get("/comics/id/{$comic->id}")->assertNotFound();
        $this->get("/comics/{$comic->slug}")->assertNotFound();
        // Pages are the comic's content: the JSON endpoint must not leak them.
        $this->getJson("/api/comics/{$comic->id}/pages")->assertNotFound();

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertSee('Only Members XYZ');
    }

    public function test_guest_sees_a_locked_teaser_that_points_at_the_login_form(): void
    {
        $comic = Comic::factory()->create(['title' => 'Locked Teaser XYZ', 'is_exclusive' => true]);

        $this->get('/')->assertOk()
            ->assertSee('data-comic-card="'.$comic->id.'"', false)
            ->assertSee('Entre para ler')
            // The members' listing tab is not advertised to guests.
            ->assertDontSee('Exclusivos');
    }

    public function test_member_reads_a_comic_scheduled_within_the_week_that_guests_cannot(): void
    {
        $comic = Comic::factory()->create(['title' => 'Soon XYZ', 'published_at' => now()->addDays(3)]);

        $this->get('/')->assertOk()
            ->assertSee('data-comic-card="'.$comic->id.'"', false)
            ->assertSee('Soon XYZ')
            ->assertSee('blur-sm', false)
            ->assertSee(route('login'), false);
        $this->get("/comics/id/{$comic->id}")->assertNotFound();

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/')->assertOk()->assertSee('Soon XYZ');

        $this->get("/comics/id/{$comic->id}")->assertOk();
    }

    public function test_member_does_not_reach_a_comic_scheduled_beyond_the_week(): void
    {
        $comic = Comic::factory()->create(['title' => 'Far Ahead XYZ', 'published_at' => now()->addDays(30)]);

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/')->assertOk()->assertDontSee('Far Ahead XYZ');

        $this->get("/comics/id/{$comic->id}")->assertNotFound();
    }

    public function test_home_list_caps_far_ahead_teasers_at_three_for_admins(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $queue = collect(range(1, 5))->map(fn (int $offset) => Comic::factory()->create([
            'title' => "Admin Queue XYZ {$offset}",
            'published_at' => now()->addDays(30 + $offset),
        ]))->values();

        $response = $this->actingAs($admin)->get('/')->assertOk();

        foreach ($queue->take(3) as $comic) {
            $response->assertSee('data-comic-card="'.$comic->id.'"', false);
        }
        foreach ($queue->slice(3) as $comic) {
            $response->assertDontSee('data-comic-card="'.$comic->id.'"', false);
        }
    }

    public function test_guest_home_list_caps_coming_soon_teasers_at_three(): void
    {
        $upcoming = collect(range(1, 5))->map(fn (int $offset) => Comic::factory()->create([
            'title' => "Em breve XYZ {$offset}",
            'published_at' => now()->addDays($offset),
        ]))->values();
        $released = Comic::factory()->create(['title' => 'Released XYZ']);

        $response = $this->get('/')->assertOk()
            ->assertSee('data-comic-card="'.$released->id.'"', false);

        foreach ($upcoming->take(3) as $comic) {
            $response->assertSee('data-comic-card="'.$comic->id.'"', false);
        }
        foreach ($upcoming->slice(3) as $comic) {
            $response->assertDontSee('data-comic-card="'.$comic->id.'"', false);
        }
    }

    public function test_member_home_list_shows_every_early_access_comic(): void
    {
        $member = User::factory()->create(['admin_level' => 0]);

        $earlyAccess = collect(range(1, 6))->map(fn (int $offset) => Comic::factory()->create([
            'title' => "Pré-estreia XYZ {$offset}",
            'published_at' => now()->addDays($offset),
        ]))->values();

        $response = $this->actingAs($member)->get('/')->assertOk();

        foreach ($earlyAccess as $comic) {
            $response->assertSee('data-comic-card="'.$comic->id.'"', false);
        }
    }

    public function test_exclusive_filter_lists_only_exclusive_comics(): void
    {
        $exclusive = Comic::factory()->create(['title' => 'Member Picked XYZ', 'is_exclusive' => true]);
        $openToAll = Comic::factory()->create(['title' => 'Open To All XYZ']);

        // Asserted on the list cards: the "most viewed" panel above the list
        // legitimately shows the open comic on the same page.
        $this->get('/?filter=exclusivo')->assertOk()
            ->assertSee('data-comic-card="'.$exclusive->id.'"', false)
            ->assertDontSee('data-comic-card="'.$openToAll->id.'"', false);
    }

    public function test_owner_marks_and_unmarks_their_own_comic_as_exclusive(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'is_exclusive' => '1'])
            ->assertRedirect();

        $this->assertTrue($comic->fresh()->isExclusive());

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'is_exclusive' => '0'])
            ->assertRedirect();

        $this->assertFalse($comic->fresh()->isExclusive());
    }

    public function test_admin_toggles_exclusivity_from_the_comics_panel(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id, 'is_exclusive' => false]);

        $this->actingAs($admin)
            ->post("/admin/comics/{$comic->id}/exclusive", ['is_exclusive' => '1'])
            ->assertRedirect(route('admin.comics'))
            ->assertSessionHas('success');

        $this->assertTrue($comic->fresh()->isExclusive());
    }

    public function test_guest_and_non_admin_cannot_toggle_exclusivity(): void
    {
        $comic = Comic::factory()->create(['title' => 'Not Yours XYZ', 'is_exclusive' => false]);

        $this->post("/admin/comics/{$comic->id}/exclusive", ['is_exclusive' => '1'])
            ->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->post("/admin/comics/{$comic->id}/exclusive", ['is_exclusive' => '1'])
            ->assertRedirect('/');

        $this->assertFalse($comic->fresh()->isExclusive());
    }

    public function test_cards_label_new_releases_exclusives_and_early_access(): void
    {
        Comic::factory()->create(['title' => 'Fresh XYZ', 'is_exclusive' => true]);
        Comic::factory()->create(['title' => 'Soon Ribbon XYZ', 'published_at' => now()->addDays(3)]);

        $owner = User::factory()->create();
        $far = Comic::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Far Ribbon XYZ',
            'published_at' => now()->addDays(30),
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Novo')
            ->assertSee('Exclusivo');

        // A member gets the last week before release and a tab to browse
        // the exclusives; the far comic stays out of their list.
        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/')->assertOk()
            ->assertSee('Pré-estreia')
            ->assertSee('Exclusivos')
            ->assertDontSee('data-comic-card="'.$far->id.'"', false);

        // ...while the owner of a comic further out sees it as "Em breve".
        $this->actingAs($owner)->get('/')->assertOk()->assertSee('Em breve');
    }
}
