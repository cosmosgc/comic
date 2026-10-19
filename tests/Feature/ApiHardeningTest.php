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

    public function test_comic_api_exposes_the_documented_fields(): void
    {
        $comic = Comic::factory()->create([
            'title' => 'Contrato',
            'author' => 'Autora',
            'slug' => 'contrato',
        ]);
        $comic->pages()->create([
            'image_path' => "comics/{$comic->id}/page-0001.jpg",
            'page_number' => 1,
        ]);

        $listed = collect($this->getJson('/api/comics?search=Contrato')->assertOk()->json())->first();

        // List endpoint: explicit scalar fields, no relation payload.
        $this->assertSame('Contrato', $listed['title']);
        $this->assertSame('contrato', $listed['slug']);
        $this->assertSame('Autora', $listed['author']);
        $this->assertArrayNotHasKey('pages', $listed);

        // Detail endpoint keeps the eager-loaded pages.
        $detail = $this->getJson("/api/comics/{$comic->id}")->assertOk()->json();
        $this->assertSame('comics/'.$comic->id.'/page-0001.jpg', $detail['pages'][0]['image_path']);
        $this->assertSame(1, $detail['pages'][0]['page_number']);
    }

    public function test_pages_api_exposes_only_public_page_fields(): void
    {
        $comic = Comic::factory()->create();
        $comic->pages()->create([
            'image_path' => "comics/{$comic->id}/page-0001.jpg",
            'page_number' => 1,
        ]);

        $page = $this->getJson("/api/comics/{$comic->id}/pages")->assertOk()->json()[0];

        $this->assertSame(
            ['id', 'comic_id', 'image_path', 'page_number', 'created_at', 'updated_at'],
            array_keys($page)
        );
    }

    public function test_collection_api_uses_the_resource_contract(): void
    {
        $owner = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Contrato',
            'user_id' => $owner->id,
            'is_public' => true,
        ]);

        $detail = $this->getJson("/api/collections/{$collection->id}")->assertOk()->json();
        $this->assertSame('Contrato', $detail['name']);
        $this->assertTrue($detail['is_public']);

        $listed = collect($this->getJson('/api/collections')->assertOk()->json())
            ->firstWhere('name', 'Contrato');
        $this->assertArrayHasKey('comics', $listed);
    }

    public function test_analytics_endpoint_stores_a_valid_event(): void
    {
        $this->postJson('/api/analytics', [
            'url' => 'https://example.test/comics',
            'user_agent' => 'PHPUnit',
            'event_type' => 'page_view',
            'device_type' => 'desktop',
            'duration' => 12,
        ])->assertCreated();

        $this->assertDatabaseHas('analytics', [
            'url' => 'https://example.test/comics',
            'event_type' => 'page_view',
            'device_type' => 'desktop',
        ]);
    }

    public function test_analytics_endpoint_rejects_an_invalid_event(): void
    {
        $this->postJson('/api/analytics', [
            'url' => 'https://example.test/comics',
            // Missing required user_agent / event_type / device_type.
            'duration' => -1,
        ])->assertStatus(422);

        $this->assertDatabaseCount('analytics', 0);
    }
}
