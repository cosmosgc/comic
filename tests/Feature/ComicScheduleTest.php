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

    public function test_scheduled_comic_hidden_from_index_and_search(): void
    {
        Comic::factory()->create(['title' => 'Visible Now XYZ', 'published_at' => null]);
        Comic::factory()->create(['title' => 'Future Hidden XYZ', 'published_at' => now()->addDay()]);

        $this->get('/')->assertOk()
            ->assertSee('Visible Now XYZ')
            ->assertDontSee('Future Hidden XYZ');

        $this->get('/comics/search?search=XYZ')->assertOk()
            ->assertSee('Visible Now XYZ')
            ->assertDontSee('Future Hidden XYZ');
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
        $comic = Comic::factory()->create(['user_id' => $owner->id, 'published_at' => now()->addDay()]);

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

    public function test_owner_can_schedule_on_update_and_clear_to_publish(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id, 'published_at' => null]);
        $future = now()->addDays(2)->format('Y-m-d\TH:i');

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

    protected function deleteComicTree(int $comicId): void
    {
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
