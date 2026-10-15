<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TagLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        $this->artisan('migrate');
        Cache::flush();
    }

    public function test_lookup_by_gallery_url_matches_local_tags(): void
    {
        Tag::create(['name' => 'stockings']);
        Tag::create(['name' => 'unrelated-local']);

        Http::fake([
            'api.e-hentai.org/*' => Http::response([
                'gmetadata' => [[
                    'gid' => 2231376,
                    'token' => 'a7584a5932',
                    'title' => 'Some gallery',
                    'category' => 'Doujinshi',
                    'rating' => '4.5',
                    'thumb' => 'https://example.test/thumb.jpg',
                    'filecount' => '10',
                    'tags' => ['female:stockings', 'female:ponytail'],
                ]],
            ], 200),
        ]);

        $this->getJson('/api/tags/lookup?q='.urlencode('https://e-hentai.org/g/2231376/a7584a5932/'))
            ->assertOk()
            ->assertJsonPath('source', 'url')
            ->assertJsonPath('galleries.0.title', 'Some gallery')
            // full "female:stockings" matches local "stockings" via short name
            ->assertJsonFragment(['full' => 'female:stockings', 'matched' => true])
            ->assertJsonFragment(['full' => 'female:ponytail', 'matched' => false])
            ->assertJsonFragment(['name' => 'stockings']);
    }

    public function test_lookup_by_title_searches_then_fetches_metadata(): void
    {
        Tag::create(['name' => 'tomboy']);

        Http::fake([
            // NOTE: api pattern first — `e-hentai.org/*` also matches the
            // api host (stubs prepend `*`), first match wins.
            'api.e-hentai.org/*' => Http::response([
                'gmetadata' => [[
                    'gid' => 999,
                    'token' => 'aaaaaaaaaa',
                    'title' => 'Found by title',
                    'tags' => ['female:tomboy'],
                ]],
            ], 200),
            // Search page returns one gallery link.
            'e-hentai.org/*' => Http::response(
                '<html><body><a href="https://e-hentai.org/g/999/aaaaaaaaaa/">result</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $this->getJson('/api/tags/lookup?q='.urlencode('some title'))
            ->assertOk()
            ->assertJsonPath('source', 'search')
            ->assertJsonPath('galleries.0.title', 'Found by title')
            ->assertJsonFragment(['name' => 'tomboy']);
    }

    public function test_lookup_requires_query(): void
    {
        $this->getJson('/api/tags/lookup')->assertStatus(422);
    }

    public function test_lookup_handles_api_error_gracefully(): void
    {
        Http::fake([
            'api.e-hentai.org/*' => Http::response(['gmetadata' => [
                ['gid' => 123, 'error' => 'Key missing, or incorrect key provided.'],
            ]], 200),
        ]);

        $this->getJson('/api/tags/lookup?q='.urlencode('https://e-hentai.org/g/123/aaaaaaaaaa/'))
            ->assertOk()
            ->assertJsonPath('galleries.0.error', 'Key missing, or incorrect key provided.')
            ->assertJsonCount(0, 'matched');
    }

    public function test_edit_page_exposes_author_field_and_title_artist_suggest(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id, 'author' => 'Some Artist']);

        $this->actingAs($owner)->get("/comics/{$comic->id}/edit")
            ->assertOk()
            ->assertSee('name="author"', false)
            ->assertSee('Sugerir por título + autor', false)
            ->assertSee('data-title-input="title"', false)
            ->assertSee('data-author-input="author"', false);
    }

    public function test_create_page_exposes_title_artist_suggest(): void
    {
        $this->actingAs(User::factory()->create())->get('/comics/create')
            ->assertOk()
            ->assertSee('Sugerir por título + autor', false)
            ->assertSee('data-title-input="title"', false)
            ->assertSee('data-author-input="author"', false);
    }
}
