<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComicAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guest_cannot_open_comic_edit(): void
    {
        $comic = Comic::factory()->create();

        $this->get("/comics/{$comic->id}/edit")->assertRedirect('/login');
    }

    public function test_guest_cannot_update_comic(): void
    {
        $comic = Comic::factory()->create();

        $this->put("/comics/{$comic->id}/update", ['title' => 'Hacked'])
            ->assertRedirect('/login');
    }

    public function test_non_owner_cannot_edit_comic(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['admin_level' => 0]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get("/comics/{$comic->id}/edit")->assertForbidden();
        $this->actingAs($intruder)->put("/comics/{$comic->id}/update", ['title' => 'Hacked'])->assertForbidden();
    }

    public function test_owner_can_edit_comic(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get("/comics/{$comic->id}/edit")->assertOk();
    }

    public function test_admin_can_edit_any_comic(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)->get("/comics/{$comic->id}/edit")->assertOk();
    }

    public function test_owner_sees_edit_button_on_comic_page(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertSee(route('comics.edit', $comic->id), false);
    }

    public function test_admin_sees_edit_button_on_comic_page(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertSee(route('comics.edit', $comic->id), false);
    }

    public function test_non_owner_and_guest_do_not_see_edit_button(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create(['admin_level' => 0]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger)->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertDontSee(route('comics.edit', $comic->id), false);

        $this->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertDontSee(route('comics.edit', $comic->id), false);
    }

    public function test_non_owner_cannot_reorder_or_delete_pages(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['admin_level' => 0]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);
        $page = $comic->pages()->create(['image_path' => 'comics/1/p1.jpg', 'page_number' => 1]);

        $this->actingAs($intruder)
            ->postJson("/comics/{$comic->id}/reorder-pages", [['id' => $page->id, 'page_number' => 1]])
            ->assertForbidden();

        $this->actingAs($intruder)->deleteJson("/page/{$page->id}")->assertForbidden();
    }

    public function test_reorder_pages_rejects_cross_comic_ids(): void
    {
        $owner = User::factory()->create();
        $mine = Comic::factory()->create(['user_id' => $owner->id]);
        $other = Comic::factory()->create();
        $foreignPage = $other->pages()->create(['image_path' => 'comics/2/p1.jpg', 'page_number' => 1]);

        // Scoped update touches zero rows instead of moving the other comic's page.
        $this->actingAs($owner)
            ->postJson("/comics/{$mine->id}/reorder-pages", [['id' => $foreignPage->id, 'page_number' => 9]])
            ->assertOk();

        $this->assertSame(1, $foreignPage->fresh()->page_number);
    }

    public function test_non_owner_cannot_add_pages(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['admin_level' => 0]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        // issues.md #14: the page-store path was the last unauthenticated
        // mutation of a comic's content.
        $this->actingAs($intruder)
            ->post("/comics/{$comic->id}/pages", ['image' => UploadedFile::fake()->image('page.jpg')])
            ->assertForbidden();

        $this->assertSame(0, $comic->pages()->count());
    }

    public function test_guest_cannot_add_pages(): void
    {
        $comic = Comic::factory()->create();

        $this->post("/comics/{$comic->id}/pages", ['image' => UploadedFile::fake()->image('page.jpg')])
            ->assertRedirect('/login');

        $this->assertSame(0, $comic->pages()->count());
    }

    public function test_owner_can_add_pages(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/comics/{$comic->id}/pages", ['image' => UploadedFile::fake()->image('page.jpg', 30, 30)])
            ->assertRedirect(route('comics.showById', $comic->id));

        $page = $comic->pages()->firstOrFail();
        $this->assertFileExists(public_path('storage/'.$page->image_path));

        @unlink(public_path('storage/'.$page->image_path));
        @rmdir(public_path("storage/comics/{$comic->id}/pages"));
        @rmdir(public_path("storage/comics/{$comic->id}"));
    }

    public function test_non_admin_cannot_access_widgets(): void
    {
        $user = User::factory()->create(['admin_level' => 0]);

        $this->actingAs($user)->get('/admin/widgets')->assertRedirect('/');
        $this->actingAs($user)->post('/admin/widgets', [
            'position_index' => 1,
            'content' => '<p>x</p>',
        ])->assertRedirect('/');
    }

    public function test_analytics_ignores_forged_identity(): void
    {
        $victim = User::factory()->create();

        $this->postJson('/api/analytics', [
            'user_id' => (string) $victim->id,
            'ip_address' => '9.9.9.9',
            'url' => 'https://example.test/comics/x',
            'user_agent' => 'TestAgent/1.0',
            'event_type' => 'page_view',
            'device_type' => 'Desktop',
        ])->assertCreated();

        $this->assertDatabaseHas('analytics', [
            'url' => 'https://example.test/comics/x',
            'user_id' => null,
        ]);
        $this->assertDatabaseMissing('analytics', ['ip_address' => '9.9.9.9']);
    }
}
