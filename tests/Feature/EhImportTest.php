<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\EhImport;
use App\Models\EhImportPage;
use App\Models\EhImportSetting;
use App\Models\Tag;
use App\Models\User;
use App\Services\EhComicImporter;
use App\Services\EhImportException;
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
        config(['ehimport.delay_jitter_ms' => 0]);
        config(['ehimport.image_delay_ms' => 0]);
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
            // Download queue seeded with both image pages, numbered.
            $import = $comic->ehImport;
            $this->assertNotNull($import);
            $this->assertSame('https://e-hentai.org/g/123/abcdef1234/', $import->source_url);
            $this->assertSame(
                [1, 2],
                $import->pages()->orderBy('page_number')->pluck('page_number')->all()
            );
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
                ->assertJsonCount(1, 'failed')
                ->assertJsonPath('errors.1', 'Página sem imagem original: https://e-hentai.org/s/aaaabbbbcc/123-1');

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

    public function test_retry_requeues_failed_pages_and_keeps_order(): void
    {
        $comicId = null;

        try {
            $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
            $comicId = $comic->id;
            $import = EhImport::create(['comic_id' => $comic->id, 'total_pages' => 3]);
            // Pages 1 and 3 already in place; page 2 failed earlier.
            foreach ([1, 3] as $number) {
                $comic->pages()->create([
                    'image_path' => "comics/{$comic->id}/pages/page-".str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.gif',
                    'page_number' => $number,
                ]);
                $import->pages()->create([
                    'page_number' => $number,
                    'page_url' => "https://e-hentai.org/s/ffffffff11/123-{$number}",
                    'status' => EhImportPage::STATUS_DONE,
                ]);
            }
            $import->pages()->create([
                'page_number' => 2,
                'page_url' => 'https://e-hentai.org/s/ffffffff11/123-2',
                'status' => EhImportPage::STATUS_FAILED,
                'attempts' => 3,
                'error' => 'boom',
            ]);

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/retry")
                ->assertOk()->assertJson(['ok' => true, 'pending' => 1]);

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 3, 'total' => 3, 'complete' => true, 'failed' => []]);

            $this->assertSame(
                [1, 2, 3],
                $comic->pages()->orderBy('page_number')->pluck('page_number')->all()
            );
            $this->assertSame("comics/{$comic->id}/pages/page-0002.gif", $comic->pages()->where('page_number', 2)->firstOrFail()->image_path);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_redownload_wipes_pages_and_imports_in_order(): void
    {
        $comicId = null;

        try {
            $comic = Comic::factory()->create(['user_id' => $this->admin->id, 'image_path' => 'comics/old.gif']);
            $comicId = $comic->id;
            $comic->pages()->create(['image_path' => 'comics/old.gif', 'page_number' => 1]);

            $this->actingAs($this->admin)->post('/admin/import/'.$comic->id.'/redownload', [
                'url' => 'https://e-hentai.org/g/123/abcdef1234/',
            ])->assertRedirect(route('admin.import.progress', $comic));

            $this->assertSame(0, $comic->pages()->count());
            $import = $comic->ehImport;
            $this->assertNotNull($import);
            $this->assertSame([1, 2], $import->pages()->orderBy('page_number')->pluck('page_number')->all());

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 2, 'total' => 2, 'complete' => true]);

            $this->assertSame(
                [1, 2],
                $comic->pages()->orderBy('page_number')->pluck('page_number')->all()
            );
            $this->assertSame("comics/{$comic->id}/pages/page-0001.gif", $comic->fresh()->image_path);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_stuck_downloading_rows_are_reclaimed(): void
    {
        $comicId = null;

        try {
            $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
            $comicId = $comic->id;
            $import = EhImport::create(['comic_id' => $comic->id, 'total_pages' => 1]);
            // A row abandoned by a dead poll 11 minutes ago.
            $row = $import->pages()->create([
                'page_number' => 1,
                'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/123-1',
                'status' => EhImportPage::STATUS_DOWNLOADING,
            ]);
            // A row abandoned by a dead poll 11 minutes ago (query update
            // bypasses guarded attributes like updated_at).
            EhImportPage::whereKey($row->id)->update(['updated_at' => now()->subMinutes(11)]);

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 1, 'total' => 1, 'complete' => true]);

            $this->assertSame(1, $comic->pages()->count());
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_pages_payload_lists_thumbs_and_pending(): void
    {
        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()
                ->assertJsonPath('pages.1', asset("storage/comics/{$comic->id}/pages/page-0001.gif"))
                ->assertJsonPath('pages.2', asset("storage/comics/{$comic->id}/pages/page-0002.gif"))
                ->assertJson(['pending' => []]);

            $this->actingAs($this->admin)->get("/admin/import/{$comic->id}/progress")
                ->assertOk()
                ->assertSee('data-page-row="1"', false)
                ->assertSee('data-page-row="2"', false);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_retry_page_requeues_a_single_failed_page(): void
    {
        $comicId = null;

        try {
            $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
            $comicId = $comic->id;
            $comic->pages()->create(['image_path' => 'x', 'page_number' => 1]);
            $import = EhImport::create(['comic_id' => $comic->id, 'total_pages' => 2]);
            $import->pages()->create([
                'page_number' => 1,
                'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/123-1',
                'status' => EhImportPage::STATUS_DONE,
            ]);
            $import->pages()->create([
                'page_number' => 2,
                'page_url' => 'https://e-hentai.org/s/ffffffff11/123-2',
                'status' => EhImportPage::STATUS_FAILED,
                'attempts' => 3,
            ]);

            $this->actingAs($this->admin)
                ->postJson("/admin/import/{$comic->id}/retry-page", ['number' => 2])
                ->assertOk()->assertJson(['ok' => true, 'pending' => 1]);

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 2, 'complete' => true, 'failed' => []]);

            $this->assertSame(
                [1, 2],
                $comic->pages()->orderBy('page_number')->pluck('page_number')->all()
            );
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_retry_page_404s_when_not_failed(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id]);

        try {
            $this->actingAs($this->admin)
                ->postJson("/admin/import/{$comic->id}/retry-page", ['number' => 9])
                ->assertNotFound();
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_import_page_shows_tuning_form_to_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/import')->assertOk()
            ->assertSee('Configurações de importação', false)
            ->assertSee('Páginas por lote', false)
            ->assertSee('Proxy de imagens', false);

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->post('/admin/import/settings', ['chunk' => 1])
            ->assertRedirect('/');
    }

    public function test_settings_save_to_db_and_apply_immediately(): void
    {
        $this->actingAs($this->admin)->post('/admin/import/settings', [
            'chunk' => 1,
            'max_per_minute' => 30,
            'image_proxy' => 'https://images.weserv.nl/?url=',
        ])->assertRedirect(route('admin.import'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('eh_import_settings', ['key' => 'chunk', 'value' => '1']);
        $this->assertDatabaseHas('eh_import_settings', ['key' => 'image_proxy', 'value' => 'https://images.weserv.nl/?url=']);
        $this->assertSame(1, config('ehimport.chunk'));
        $this->assertSame(30, config('ehimport.max_per_minute'));

        // Untouched keys keep their values and no rows.
        $this->assertSame(0, config('ehimport.delay_ms')); // zeroed in setUp
        $this->assertDatabaseMissing('eh_import_settings', ['key' => 'delay_ms']);
    }

    public function test_empty_setting_resets_to_default(): void
    {
        EhImportSetting::create(['key' => 'chunk', 'value' => '5']);

        $this->actingAs($this->admin)->post('/admin/import/settings', ['chunk' => ''])
            ->assertRedirect(route('admin.import'));

        $this->assertDatabaseMissing('eh_import_settings', ['key' => 'chunk']);
    }

    public function test_settings_reject_out_of_range_values(): void
    {
        $this->actingAs($this->admin)->post('/admin/import/settings', ['chunk' => 999])
            ->assertSessionHasErrors('chunk');

        $this->assertDatabaseMissing('eh_import_settings', ['key' => 'chunk']);
    }

    public function test_saved_chunk_size_drives_downloads(): void
    {
        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            $this->actingAs($this->admin)->post('/admin/import/settings', ['chunk' => 1])
                ->assertRedirect();

            // Two pages, one per poll with chunk=1.
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 1, 'complete' => false]);
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 2, 'complete' => true]);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_download_falls_back_to_proxy_when_direct_fails(): void
    {
        config(['ehimport.image_proxy' => 'https://images.weserv.nl/?url=']);
        $gif = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
        Http::fake([
            'blocked.test/*' => Http::failedConnection(),
            'images.weserv.nl/*' => Http::response($gif, 200, ['Content-Type' => 'image/gif']),
        ]);

        $result = app(EhComicImporter::class)->downloadImage('https://blocked.test:43456/h/abc.jpg');

        $this->assertSame($gif, $result['bytes']);
        $this->assertSame('gif', $result['extension']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://images.weserv.nl/'));
    }

    public function test_download_without_proxy_throws_original_error(): void
    {
        config(['ehimport.image_proxy' => '']);
        Http::fake(['blocked.test/*' => Http::failedConnection()]);

        $this->expectException(EhImportException::class);
        app(EhComicImporter::class)->downloadImage('https://blocked.test:43456/h/abc.jpg');
    }

    public function test_retry_resolves_a_fresh_image_url(): void
    {
        $comicId = null;

        try {
            $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
            $comicId = $comic->id;
            $import = EhImport::create(['comic_id' => $comic->id, 'total_pages' => 1]);
            $import->pages()->create([
                'page_number' => 1,
                'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/123-1',
                'image_url' => 'https://img.test/stale.jpg',
                'status' => EhImportPage::STATUS_PENDING,
                'attempts' => 1,
            ]);

            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 1, 'complete' => true]);

            // Stale keystamp replaced by a fresh resolve (setUp /s/ fake
            // serves https://img.test/full.jpg).
            Http::assertSent(fn ($request) => str_contains($request->url(), '/s/aaaabbbbcc/123-1'));
            $this->assertSame(
                'https://img.test/full.jpg',
                $import->pages()->firstOrFail()->image_url
            );
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_rate_limit_returns_429_with_retry_after(): void
    {
        config(['ehimport.max_per_minute' => 1]);

        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            // First poll consumes the single hit (2 pages: 1 done, 1 stopped by the ceiling).
            $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")->assertOk();

            // Nothing processed on the next poll → 429 + Retry-After.
            $response = $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages");
            $response->assertStatus(429);
            $this->assertNotNull($response->headers->get('Retry-After'));
            $this->assertSame(1, $comic->pages()->count());
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
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

    public function test_payload_reports_sizes_details_log_and_file_download(): void
    {
        $comicId = null;

        try {
            $comic = $this->storedComic();
            $comicId = $comic->id;

            $response = $this->actingAs($this->admin)->postJson("/admin/import/{$comic->id}/pages")
                ->assertOk()->assertJson(['done' => 2, 'total' => 2, 'complete' => true]);

            $gifBytes = strlen(base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
            $response->assertJsonPath('total_size', $gifBytes * 2)
                ->assertJsonPath('details.1.status', 'done')
                ->assertJsonPath('details.1.size', $gifBytes)
                ->assertJsonPath('details.2.status', 'done');
            $log = $response->json('log');
            $this->assertNotEmpty($log);
            $this->assertStringContainsString('Concluído', end($log));

            $this->actingAs($this->admin)->get("/admin/import/{$comic->id}/progress")
                ->assertOk()
                ->assertSee('import-modal', false)
                ->assertSee('Log (', false);

            // Download the stored file to this computer.
            $this->actingAs($this->admin)->get("/admin/import/{$comic->id}/pages/1/file")
                ->assertOk()
                ->assertDownload('page-0001.gif');

            // Missing page → 404.
            $this->actingAs($this->admin)->get("/admin/import/{$comic->id}/pages/99/file")->assertNotFound();
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_non_admin_cannot_download_page_file(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id]);

        try {
            $this->actingAs(User::factory()->create(['admin_level' => 0]))
                ->get("/admin/import/{$comic->id}/pages/1/file")
                ->assertRedirect('/');
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    protected function deleteComicTree(int $comicId): void
    {
        Tag::query()->whereDoesntHave('comics')->delete();
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
