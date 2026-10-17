<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Comic;
use App\Models\User;
use Tests\TestCase;

/**
 * Public JSON API hardening (issues.md #9): no unauthenticated writes,
 * no scheduled/private data leaks and bounded payloads.
 */
class ApiHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_slug_backfill_endpoint_is_no_longer_public(): void
    {
        // Was an unauthenticated GET that wrote to every comic row; the
        // maintenance job is now `php artisan comics:backfill-slugs`.
        $this->getJson('/api/comics/update-slugs')->assertNotFound();
    }

    public function test_pages_endpoint_hides_scheduled_comics(): void
    {
        $comic = Comic::factory()->create(['published_at' => now()->addDay()]);
        $comic->pages()->create([
            'image_path' => "comics/{$comic->id}/page-0001.jpg",
            'page_number' => 1,
        ]);

        $this->getJson("/api/comics/{$comic->id}/pages")->assertNotFound();
    }

    public function test_pages_endpoint_returns_pages_for_published_comics(): void
    {
        $comic = Comic::factory()->create();
        $comic->pages()->create([
            'image_path' => "comics/{$comic->id}/page-0001.jpg",
            'page_number' => 1,
        ]);

        $response = $this->getJson("/api/comics/{$comic->id}/pages")->assertOk();

        $this->assertCount(1, $response->json());
    }

    public function test_search_never_returns_scheduled_comics(): void
    {
        Comic::factory()->create(['title' => 'Publicado', 'author' => 'Zezinho']);
        Comic::factory()->create([
            'title' => 'Rascunho',
            'author' => 'Zezinho',
            'published_at' => now()->addWeek(),
        ]);

        // The author branch of the search used to escape the published()
        // scope, leaking the title of an unreleased comic.
        $titles = collect($this->getJson('/api/comics?search=Zezinho')->assertOk()->json())
            ->pluck('title')
            ->all();

        $this->assertSame(['Publicado'], $titles);
    }

    public function test_browse_listing_is_capped(): void
    {
        Comic::factory()->count(105)->create();

        $this->assertCount(100, $this->getJson('/api/comics')->assertOk()->json());
    }

    public function test_limit_parameter_is_validated(): void
    {
        $this->getJson('/api/comics?limit=-5')->assertStatus(422);
        $this->getJson('/api/comics?limit=1000')->assertStatus(422);
        $this->getJson('/api/collections?limit=0')->assertStatus(422);
    }

    public function test_private_collections_are_hidden_from_the_public_api(): void
    {
        $owner = User::factory()->create();

        $private = Collection::create([
            'name' => 'Favoritos',
            'user_id' => $owner->id,
            'is_public' => false,
        ]);
        Collection::create([
            'name' => 'Públicos',
            'user_id' => $owner->id,
            'is_public' => true,
        ]);

        // A private collection (a user's Favorites is is_public = false)
        // must not be readable by guessing its id…
        $this->getJson("/api/collections/{$private->id}")->assertNotFound();

        // …nor be listed.
        $names = collect($this->getJson('/api/collections')->assertOk()->json())->pluck('name');
        $this->assertTrue($names->contains('Públicos'));
        $this->assertFalse($names->contains('Favoritos'));
    }
}
