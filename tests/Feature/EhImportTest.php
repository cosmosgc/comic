<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EhImportTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        config(['ehimport.delay_ms' => 0]);
        $this->artisan('migrate');
        Cache::flush();

        $this->admin = User::factory()->create(['admin_level' => 1]);

        Http::preventStrayRequests();
        Http::fake([
            // NOTE: api pattern first — `e-hentai.org/*` also matches the api host.
            'api.e-hentai.org/*' => Http::response([
                'gmetadata' => [[
                    'gid' => 123,
                    'token' => 'abcdef1234',
                    'title' => 'Imported Title',
                    'title_jpn' => 'Japanese Title',
                    'category' => 'Doujinshi',
                    'rating' => '4.5',
                    'thumb' => 'https://img.test/thumb.jpg',
                    'filecount' => '2',
                    'tags' => ['artist:someone', 'female:stockings', 'language:portuguese'],
                ]],
            ], 200),
            'e-hentai.org/g/*' => Http::response(
                '<html><body>'.
                '<a href="https://e-hentai.org/s/aaaabbbbcc/123-1"><img src="t1.jpg"></a>'.
                '<a href="https://e-hentai.org/s/ddddeeee11/123-2"><img src="t2.jpg"></a>'.
                '</body></html>',
                200
            ),
            'e-hentai.org/s/*' => Http::response(
                '<html><body><div><img id="img" src="https://img.test/full.jpg"></div></body></html>',
                200
            ),
            'img.test/*' => Http::response(
                base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='),
                200,
                ['Content-Type' => 'image/gif']
            ),
        ]);
    }

    public function test_guest_and_non_admin_cannot_open_import(): void
    {
        // Guests hit `auth` (→ /login); plain users hit AdminMiddleware (→ /).
        $this->get('/admin/import')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/admin/import')->assertRedirect('/');
    }

    public function test_preview_rejects_invalid_url(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/import/preview', ['url' => 'not a gallery url'])
            ->assertRedirect(route('admin.import'))
            ->assertSessionHas('error');
    }

    public function test_preview_shows_metadata_and_defaults_to_tomorrow(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/admin/import/preview', ['url' => 'https://e-hentai.org/g/123/abcdef1234/']);

        $response->assertOk()
            ->assertSee('Imported Title', false)
            ->assertSee('someone', false)
            ->assertSee('female:stockings', false)
            ->assertSee('2 página(s)', false)
            ->assertSee(now()->addDay()->format('Y-m-d'), false);
    }

    public function test_default_date_follows_latest_scheduled(): void
    {
        Comic::factory()->create(['user_id' => $this->admin->id, 'published_at' => now()->addDays(2)]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/import/preview', ['url' => 'https://e-hentai.org/g/123/abcdef1234/']);

        $response->assertOk()
            ->assertSee(now()->addDays(3)->format('Y-m-d'), false);
    }

    public function test_store_creates_scheduled_comic_with_short_tags(): void
    {
        $comicId = null;

        try {
            $token = $this->previewToken();
            $future = now()->addDays(4)->format('Y-m-d\\TH:i');

            $this->actingAs($this->admin)->post('/admin/import', [
                'preview_token' => $token,
                'title' => 'Stored Import',
                'author' => 'someone',
                'description' => 'desc',
                'tags' => 'female:stockings, artist:someone, custom tag',
                'published_at' => $future,
            ])->assertRedirect();

            $comic = Comic::where('title', 'Stored Import')->firstOrFail();
            $comicId = $comic->id;

            $this->assertTrue($comic->isScheduled());
            $this->assertSame($this->admin->id, (int) $comic->user_id);
            // Namespaced imports store short names, like the tag editor.
            $names = $comic->tags->pluck('name')->all();
            $this->assertContains('stockings', $names);
            $this->assertContains('someone', $names);
            $this->assertContains('custom tag', $names);
            $this->assertNotContains('female:stockings', $names);
            // Download queue seeded with both image pages.
            $state = Cache::get("ehimport:state:{$comic->id}");
            $this->assertCount(2, $state['remaining']);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_pages_chunk_downloads_images_and_sets_cover(): void
    {
        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            $this->actingAs($this->admin)
                ->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()
                ->assertJson(['done' => 2, 'total' => 2, 'complete' => true, 'failed' => []]);

            $this->assertSame(2, $comic->pages()->count());
            $cover = $comic->fresh()->image_path;
            $this->assertSame('comics/'.$comic->id.'/pages/page-0001.gif', $cover);
            $this->assertFileExists(public_path('storage/'.$cover));
            // Strangers still 404 until the scheduled date (actingAs
            // persists across requests, so check as someone else).
            $this->actingAs(User::factory()->create(['admin_level' => 0]))
                ->get("/comics/id/{$comic->id}")->assertNotFound();
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_expired_preview_token_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/import', [
            'preview_token' => str_repeat('a', 32),
            'title' => 'Nope',
        ])->assertRedirect(route('admin.import'))
            ->assertSessionHas('error');
    }

    public function test_failed_downloads_are_reported_after_retries(): void
    {
        // Stubs accumulate per fake() call (first match wins), so reset
        // the factory to model a gallery whose image page has no image.
        Http::swap(new HttpClientFactory);
        Http::preventStrayRequests();
        Http::fake([
            'api.e-hentai.org/*' => Http::response(['gmetadata' => [[
                'gid' => 123, 'token' => 'abcdef1234', 'title' => 'T',
                'filecount' => '1', 'tags' => [],
            ]]], 200),
            'e-hentai.org/g/*' => Http::response(
                '<a href="https://e-hentai.org/s/aaaabbbbcc/123-1">p1</a>', 200
            ),
            'e-hentai.org/s/*' => Http::response('no image here', 200),
        ]);

        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            // Attempts 1-2 requeue the page; the 3rd gives up.
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 0, 'complete' => false]);
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 0, 'complete' => false]);
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 0, 'complete' => true])
                ->assertJsonCount(1, 'failed');

            $this->assertSame(0, $comic->pages()->count());
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    /**
     * Run preview → store and return the created comic.
     */
    protected function storedComic(): Comic
    {
        $token = $this->previewToken();

        $response = $this->actingAs($this->admin)->post('/admin/import', [
            'preview_token' => $token,
            'title' => 'Chunk Import '.uniqid(),
            'author' => 'someone',
            'tags' => 'female:stockings',
            'published_at' => now()->addDay()->format('Y-m-d\\TH:i'),
        ]);
        $response->assertRedirect();

        return Comic::orderByDesc('id')->firstOrFail();
    }

    protected function previewToken(): string
    {
        $html = $this->actingAs($this->admin)
            ->post('/admin/import/preview', ['url' => 'https://e-hentai.org/g/123/abcdef1234/'])
            ->assertOk()
            ->getContent();

        preg_match('/name="preview_token" value="([A-Za-z0-9]{32})"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null, 'Preview did not render a token.');

        return $m[1];
    }

    protected function deleteComicTree(int $comicId): void
    {
        Tag::query()->whereDoesntHave('comics')->delete();
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
