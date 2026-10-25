<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\EhImport;
use App\Models\EhImportPage;
use App\Models\EhImportQueueItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EhImportQueueTest extends TestCase
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
        config(['ehimport.title_cleanup' => []]);
        $this->artisan('migrate');
        Cache::flush();

        $this->admin = User::factory()->create(['admin_level' => 1]);

        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            // gdata: answer for whatever gid/token pair was requested.
            if (str_contains($url, 'api.e-hentai.org')) {
                if (($request->data()['method'] ?? '') === 'gtoken') {
                    return Http::response(['tokenlist' => [['token' => 'aaaabbbbcc']]], 200);
                }

                $gmetadata = [];
                foreach ($request->data()['gidlist'] ?? [] as $pair) {
                    $gid = (int) ($pair[0] ?? 0);
                    $gmetadata[] = [
                        'gid' => $gid,
                        'token' => (string) ($pair[1] ?? ''),
                        // 4242 mimics real E-Hentai titles with both the
                        // Japanese and translated names, far past 255 chars.
                        'title' => $gid === 4242 ? str_repeat('Very Long Gallery Title ', 20) : 'Search Gallery '.$gid,
                        'category' => 'Doujinshi',
                        'thumb' => 'https://img.test/thumb-'.$gid.'.jpg',
                        'filecount' => '1',
                        'uploader' => 'up'.$gid,
                        'tags' => ['artist:artist'.$gid, 'language:portuguese'],
                    ];
                }

                return Http::response(['gmetadata' => $gmetadata], 200);
            }

            if (preg_match('#e-hentai\.org/g/(\d+)/#', $url, $m) === 1) {
                return Http::response(
                    '<a href="https://e-hentai.org/s/aaaabbbbcc/'.$m[1].'-1">page 1</a>',
                    200
                );
            }

            if (str_contains($url, 'e-hentai.org/s/')) {
                return Http::response('<html><body><img id="img" src="https://img.test/full.jpg"></body></html>', 200);
            }

            if (str_contains($url, 'e-hentai.org/?')) {
                return Http::response($this->searchHtml(), 200);
            }

            if (str_contains($url, 'img.test')) {
                return Http::response(
                    base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='),
                    200,
                    ['Content-Type' => 'image/gif']
                );
            }

            return Http::response('', 404);
        });
    }

    public function test_search_renders_results_with_the_next_cursor(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/import/search?q=furry')
            ->assertOk()
            ->assertSee('Cat Furry One', false)
            ->assertSee('Dog Furry Two', false)
            ->assertSee('Doujinshi', false)
            ->assertSee('12 página(s)', false)
            ->assertSee('up123', false)
            ->assertSee('página 1', false)
            ->assertSee('next=999', false)
            ->assertSee('Adicionar selecionadas à fila', false)
            // Only the first page has no previous cursor.
            ->assertSee('Primeira', false);
    }

    public function test_search_accepts_a_pasted_search_url_and_keeps_its_cursor(): void
    {
        $url = 'https://e-hentai.org/?f_search=furry&next=555';

        $this->actingAs($this->admin)
            ->get('/admin/import/search?q='.urlencode($url))
            ->assertOk()
            ->assertSee('Cat Furry One', false);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'f_search=furry')
            && str_contains($request->url(), 'next=555'));
    }

    public function test_search_marks_galleries_that_were_already_imported(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Already In Site']);
        EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 123,
            'token' => 'abcdef0123',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
        ]);

        EhImportQueueItem::create([
            'gid' => 456,
            'token' => 'aaaabbbbcc',
            'title' => 'Queued Already',
            'source_url' => 'https://e-hentai.org/g/456/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_PENDING,
        ]);

        try {
            $this->actingAs($this->admin)
                ->get('/admin/import/search?q=furry')
                ->assertOk()
                ->assertSee('Já importado', false)
                ->assertSee('Already In Site', false)
                ->assertSee('Na fila (pendente)', false);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_a_gallery_url_in_the_search_box_points_to_the_gallery_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/import/search?q='.urlencode('https://e-hentai.org/g/123/abcdef0123/'))
            ->assertRedirect(route('admin.import').'#import-gallery')
            ->assertSessionHas('error')
            ->assertSessionHasInput('url', 'https://e-hentai.org/g/123/abcdef0123/');
    }

    public function test_queue_store_adds_selected_galleries_with_canonical_metadata(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/import/queue', ['items' => ['123:abcdef0123', '456:aaaabbbbcc']])
            ->assertRedirect(route('admin.import').'#import-queue')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('eh_import_queue', [
            'gid' => 123,
            'title' => 'Search Gallery 123',
            'status' => EhImportQueueItem::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('eh_import_queue', [
            'gid' => 456,
            'title' => 'Search Gallery 456',
            'source_url' => 'https://e-hentai.org/g/456/aaaabbbbcc/',
        ]);

        // Selecting the same galleries again does not duplicate the rows.
        $this->actingAs($this->admin)
            ->post('/admin/import/queue', ['items' => ['123:abcdef0123', '456:aaaabbbbcc']])
            ->assertSessionHas('error');

        $this->assertSame(2, EhImportQueueItem::count());
    }

    public function test_queue_store_skips_galleries_that_are_already_imported(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
        EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 123,
            'token' => 'abcdef0123',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
        ]);

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['123:abcdef0123']])
                ->assertRedirect(route('admin.import').'#import-queue')
                ->assertSessionHas('error');

            $this->assertDatabaseCount('eh_import_queue', 0);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_queue_store_validates_the_selected_items(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/import/queue', ['items' => ['not-a-gallery']])
            ->assertSessionHasErrors('items.0');
    }

    public function test_processing_the_queue_creates_a_scheduled_comic_and_downloads_it(): void
    {
        $comicId = null;

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['123:abcdef0123']]);

            $response = $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('comic.title', 'Search Gallery 123')
                ->assertJsonPath('comic.complete', true)
                ->assertJsonPath('queue.done', 1)
                ->assertJsonPath('done', true);

            $comicId = (int) $response->json('comic.id');
            $comic = Comic::findOrFail($comicId);

            $this->assertTrue($comic->isScheduled());
            $this->assertSame('artist123', $comic->author);
            $this->assertSame($this->admin->id, (int) $comic->user_id);
            $this->assertNotNull($comic->ehImport);
            $this->assertSame(123, (int) $comic->ehImport->gid);
            $this->assertSame(1, $comic->pages()->count());
            $this->assertFileExists(public_path('storage/'.$comic->image_path));

            $this->assertDatabaseHas('eh_import_queue', [
                'gid' => 123,
                'comic_id' => $comic->id,
                'status' => EhImportQueueItem::STATUS_DONE,
            ]);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_queue_processing_reports_the_client_side_pause_before_the_next_batch(): void
    {
        config([
            'ehimport.image_delay_ms' => 1000,
            'ehimport.delay_ms' => 1000,
            'ehimport.delay_jitter_ms' => 500,
        ]);

        $comicId = null;

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['321:abcdef0123']])
                ->assertSessionHas('success');

            $response = $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                // 1 image → 1000 (imagem) + 2×1000 (pausas) + 500 (jitter).
                ->assertJsonPath('next_delay_ms', 3500);

            $comicId = (int) $response->json('comic.id');
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_batch_import_cleans_configured_snippets_from_the_comic_title(): void
    {
        config(['ehimport.title_cleanup' => ['Gallery']]);
        $comicId = null;

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['123:abcdef0123']])
                ->assertSessionHas('success');

            // The queue row is cleaned right away, before processing.
            $this->assertDatabaseHas('eh_import_queue', ['gid' => 123, 'title' => 'Search 123']);

            $response = $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('comic.title', 'Search 123');

            $comicId = (int) $response->json('comic.id');
            $this->assertSame('Search 123', Comic::findOrFail($comicId)->title);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_processing_skips_a_gallery_that_is_already_in_the_site(): void
    {
        $existing = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Already Imported']);
        EhImport::create([
            'comic_id' => $existing->id,
            'gid' => 123,
            'token' => 'abcdef0123',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
        ]);
        EhImportQueueItem::create([
            'gid' => 123,
            'token' => 'abcdef0123',
            'title' => 'Queued Duplicate',
            'source_url' => 'https://e-hentai.org/g/123/abcdef0123/',
            'status' => EhImportQueueItem::STATUS_PENDING,
        ]);

        try {
            $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('skipped', true)
                ->assertJsonPath('queue.skipped', 1);

            $item = EhImportQueueItem::firstOrFail();
            $this->assertSame(EhImportQueueItem::STATUS_SKIPPED, $item->status);
            $this->assertSame($existing->id, (int) $item->comic_id);
            $this->assertStringContainsString('Já importado', (string) $item->error);
            $this->assertSame(1, Comic::count());
        } finally {
            $this->deleteComicTree($existing->id);
        }
    }

    public function test_retrying_a_queue_item_requeues_its_failed_pages(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id]);
        $import = EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 123,
            'token' => 'abcdef0123',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
        ]);
        $import->pages()->create([
            'page_number' => 1,
            'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/123-1',
            'status' => EhImportPage::STATUS_FAILED,
            'attempts' => 3,
            'error' => 'boom',
        ]);
        $item = EhImportQueueItem::create([
            'gid' => 123,
            'token' => 'abcdef0123',
            'title' => 'Finished With Failures',
            'source_url' => 'https://e-hentai.org/g/123/abcdef0123/',
            'status' => EhImportQueueItem::STATUS_DONE,
            'comic_id' => $comic->id,
            'error' => '1 página(s) falharam',
        ]);

        try {
            $this->actingAs($this->admin)
                ->post("/admin/import/queue/{$item->id}/retry")
                ->assertRedirect(route('admin.import').'#import-queue');

            $this->assertSame(EhImportQueueItem::STATUS_PENDING, $item->fresh()->status);
            $this->assertSame(EhImport::STATUS_DOWNLOADING, $import->fresh()->status);

            $page = $import->pages()->firstOrFail();
            $this->assertSame(EhImportPage::STATUS_PENDING, $page->status);
            $this->assertSame(0, (int) $page->attempts);
            $this->assertNull($page->error);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_queue_items_can_be_removed_and_finished_rows_cleared(): void
    {
        $pending = EhImportQueueItem::create([
            'gid' => 111,
            'token' => 'aaaabbbbcc',
            'title' => 'Pending One',
            'source_url' => 'https://e-hentai.org/g/111/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_PENDING,
        ]);
        EhImportQueueItem::create([
            'gid' => 222,
            'token' => 'aaaabbbbcc',
            'title' => 'Finished One',
            'source_url' => 'https://e-hentai.org/g/222/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_DONE,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/import/queue/{$pending->id}")
            ->assertRedirect(route('admin.import').'#import-queue');

        $this->assertDatabaseMissing('eh_import_queue', ['id' => $pending->id]);

        $this->actingAs($this->admin)
            ->post('/admin/import/queue/clear')
            ->assertRedirect(route('admin.import').'#import-queue');

        $this->assertDatabaseCount('eh_import_queue', 0);
    }

    public function test_queue_items_can_be_reordered_and_the_new_order_is_processed_first(): void
    {
        $comicId = null;

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['111:aaaabbbbcc', '222:aaaabbbbcc']])
                ->assertSessionHas('success');

            $id111 = (int) EhImportQueueItem::where('gid', 111)->value('id');
            $id222 = (int) EhImportQueueItem::where('gid', 222)->value('id');
            $this->assertSame(1, (int) EhImportQueueItem::where('gid', 111)->value('position'));
            $this->assertSame(2, (int) EhImportQueueItem::where('gid', 222)->value('position'));

            $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/reorder', ['ids' => [$id222, $id111]])
                ->assertOk()
                ->assertJsonPath('order', [$id222, $id111]);

            $this->assertSame(1, (int) EhImportQueueItem::where('gid', 222)->value('position'));
            $this->assertSame(2, (int) EhImportQueueItem::where('gid', 111)->value('position'));

            // A partial payload keeps the rows that were not sent after the sent ones.
            $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/reorder', ['ids' => [$id111]])
                ->assertOk()
                ->assertJsonPath('order', [$id111, $id222]);

            $response = $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('comic.title', 'Search Gallery 111')
                ->assertJsonPath('queue.done', 1);

            $comicId = (int) $response->json('comic.id');
            $this->assertSame(111, (int) Comic::findOrFail($comicId)->ehImport->gid);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_a_gallery_url_can_be_added_directly_to_the_queue(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/import/queue/add', ['url' => 'https://e-hentai.org/g/777/aaaabbbbcc/'])
            ->assertRedirect(route('admin.import').'#import-queue')
            ->assertSessionHas('success');

        $item = EhImportQueueItem::firstOrFail();
        $this->assertSame(777, (int) $item->gid);
        $this->assertSame('Search Gallery 777', $item->title);
        $this->assertSame(1, (int) $item->position);
        $this->assertFalse($item->redownload);

        // The same gallery cannot be queued twice.
        $this->actingAs($this->admin)
            ->post('/admin/import/queue/add', ['url' => 'https://e-hentai.org/g/777/aaaabbbbcc/'])
            ->assertSessionHas('error');
        $this->assertSame(1, EhImportQueueItem::count());

        // Imported galleries belong to "Adicionar comics já no site".
        $comic = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Imported Already']);
        EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 888,
            'token' => 'aaaabbbbcc',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
        ]);

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue/add', ['url' => 'https://e-hentai.org/g/888/aaaabbbbcc/'])
                ->assertSessionHas('error');
            $this->assertSame(1, EhImportQueueItem::count());
        } finally {
            $this->deleteComicTree($comic->id);
        }

        // Anything that is not an E-Hentai gallery/page URL is refused.
        $this->actingAs($this->admin)
            ->post('/admin/import/queue/add', ['url' => 'https://example.com/not-a-gallery'])
            ->assertSessionHas('error');
        $this->assertSame(1, EhImportQueueItem::count());
    }

    public function test_an_image_page_url_resolves_the_gallery_token(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/import/queue/add', ['url' => 'https://e-hentai.org/s/aaaabbbbcc/777-1'])
            ->assertSessionHas('success');

        $item = EhImportQueueItem::firstOrFail();
        $this->assertSame(777, (int) $item->gid);
        $this->assertSame('aaaabbbbcc', $item->token);
        $this->assertSame('Search Gallery 777', $item->title);
    }

    public function test_an_existing_comic_can_be_added_to_the_queue_for_its_pending_pages(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Pending Comic']);
        $import = EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 321,
            'token' => 'aaaabbbbcc',
            'status' => EhImport::STATUS_DOWNLOADING,
            'total_pages' => 2,
            'source_url' => 'https://e-hentai.org/g/321/aaaabbbbcc/',
        ]);
        $failed = $import->pages()->create([
            'page_number' => 1,
            'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/321-1',
            'status' => EhImportPage::STATUS_FAILED,
            'attempts' => 3,
            'error' => 'boom',
        ]);
        $import->pages()->create([
            'page_number' => 2,
            'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/321-2',
            'status' => EhImportPage::STATUS_DONE,
        ]);

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue/add-comic', ['comic_id' => $comic->id])
                ->assertRedirect(route('admin.import').'#import-queue')
                ->assertSessionHas('success');

            $item = EhImportQueueItem::firstOrFail();
            $this->assertSame($comic->id, (int) $item->comic_id);
            $this->assertSame(321, (int) $item->gid);
            $this->assertFalse($item->redownload);

            // Failed pages get a fresh round of attempts.
            $this->assertSame(EhImportPage::STATUS_PENDING, $failed->fresh()->status);
            $this->assertSame(0, (int) $failed->fresh()->attempts);
            $this->assertNull($failed->fresh()->error);

            // The same comic cannot be queued twice.
            $this->actingAs($this->admin)
                ->post('/admin/import/queue/add-comic', ['comic_id' => $comic->id])
                ->assertSessionHas('error');
            $this->assertSame(1, EhImportQueueItem::count());

            // A fully downloaded comic is only accepted with the redownload flag.
            $complete = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Complete Comic']);
            $completeImport = EhImport::create([
                'comic_id' => $complete->id,
                'gid' => 654,
                'token' => 'aaaabbbbcc',
                'status' => EhImport::STATUS_COMPLETE,
                'total_pages' => 1,
                'source_url' => 'https://e-hentai.org/g/654/aaaabbbbcc/',
            ]);
            $completeImport->pages()->create([
                'page_number' => 1,
                'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/654-1',
                'status' => EhImportPage::STATUS_DONE,
            ]);

            try {
                $this->actingAs($this->admin)
                    ->post('/admin/import/queue/add-comic', ['comic_id' => $complete->id])
                    ->assertSessionHas('error');
                $this->assertSame(1, EhImportQueueItem::count());

                $this->actingAs($this->admin)
                    ->post('/admin/import/queue/add-comic', ['comic_id' => $complete->id, 'redownload' => 1])
                    ->assertSessionHas('success');

                $this->assertTrue((bool) EhImportQueueItem::where('gid', 654)->value('redownload'));
            } finally {
                EhImportQueueItem::where('gid', 654)->delete();
                $this->deleteComicTree($complete->id);
            }
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_a_redownload_queue_item_wipes_and_reseeds_the_comic_pages(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->admin->id, 'title' => 'Old Comic']);
        $import = EhImport::create([
            'comic_id' => $comic->id,
            'gid' => 123,
            'token' => 'abcdef0123',
            'status' => EhImport::STATUS_COMPLETE,
            'total_pages' => 1,
            'source_url' => 'https://e-hentai.org/g/123/abcdef0123/',
        ]);
        $oldImportId = (int) $import->id;
        $oldPage = $import->pages()->create([
            'page_number' => 1,
            'page_url' => 'https://e-hentai.org/s/aaaabbbbcc/123-1',
            'status' => EhImportPage::STATUS_DONE,
        ]);

        $item = EhImportQueueItem::create([
            'gid' => 123,
            'token' => 'abcdef0123',
            'title' => 'Old Comic',
            'source_url' => 'https://e-hentai.org/g/123/abcdef0123/',
            'status' => EhImportQueueItem::STATUS_PENDING,
            'comic_id' => $comic->id,
            'redownload' => true,
            'position' => 1,
        ]);

        try {
            $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('comic.id', $comic->id)
                ->assertJsonPath('queue.done', 1);

            $this->assertDatabaseMissing('eh_import_pages', ['id' => $oldPage->id]);

            $fresh = $comic->fresh();
            $this->assertNotNull($fresh->ehImport);
            $this->assertNotSame($oldImportId, (int) $fresh->ehImport->id);
            $this->assertSame(1, $fresh->ehImport->pages()->count());
            $this->assertSame(EhImportPage::STATUS_DONE, $fresh->ehImport->pages()->firstOrFail()->status);

            // The flag is cleared so a retry does not wipe everything again.
            $this->assertFalse((bool) $item->fresh()->redownload);
            $this->assertSame(EhImportQueueItem::STATUS_DONE, $item->fresh()->status);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_the_queue_card_renders_drag_handles_and_add_forms(): void
    {
        EhImportQueueItem::create([
            'gid' => 999,
            'token' => 'aaaabbbbcc',
            'title' => 'Queue Card Gallery',
            'source_url' => 'https://e-hentai.org/g/999/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_PENDING,
            'position' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/import?sort=position')
            ->assertOk()
            ->assertSee('data-queue-order-url', false)
            ->assertSee('data-queue-drag', false)
            ->assertSee('data-queue-item="'.EhImportQueueItem::firstOrFail()->id.'"', false)
            ->assertSee('Adicionar por URL', false)
            ->assertSee('Adicionar comics já no site', false)
            ->assertSee('Baixar todas as páginas de novo', false);
    }

    public function test_the_queue_card_paginates_sorts_by_most_recent_and_filters_by_status(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $item = EhImportQueueItem::create([
                'gid' => 9000 + $i,
                'token' => 'aaaabbbbcc',
                'title' => 'Queue Item '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'source_url' => 'https://e-hentai.org/g/'.(9000 + $i).'/aaaabbbbcc/',
                'status' => EhImportQueueItem::STATUS_PENDING,
                'position' => $i,
            ]);
            // Item 01 is the oldest, item 11 the newest.
            $item->forceFill([
                'created_at' => now()->subMinutes(20 - $i),
                'updated_at' => now()->subMinutes(20 - $i),
            ])->save();
        }

        EhImportQueueItem::create([
            'gid' => 9100,
            'token' => 'aaaabbbbcc',
            'title' => 'Finished Download',
            'source_url' => 'https://e-hentai.org/g/9100/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_DONE,
            'position' => 12,
        ]);

        // Default: most recent first, 10 rows per page.
        $this->actingAs($this->admin)
            ->get('/admin/import')
            ->assertOk()
            ->assertSee('Queue Item 11', false)
            ->assertSee('Queue Item 04', false)
            ->assertDontSee('Queue Item 01', false)
            ->assertSee('Página 1 de 2', false);

        $this->actingAs($this->admin)
            ->get('/admin/import?page=2')
            ->assertOk()
            ->assertSee('Queue Item 01', false)
            ->assertDontSee('Queue Item 11', false);

        // Explicit ordering.
        $this->actingAs($this->admin)
            ->get('/admin/import?sort=oldest')
            ->assertOk()
            ->assertSee('Queue Item 01', false)
            ->assertDontSee('Queue Item 11', false);

        // Status filters: queue (pending/failed), importing and finished.
        $this->actingAs($this->admin)
            ->get('/admin/import?status=queued')
            ->assertOk()
            ->assertSee('Queue Item 11', false)
            ->assertDontSee('Finished Download', false);

        $this->actingAs($this->admin)
            ->get('/admin/import?status=done')
            ->assertOk()
            ->assertSee('Finished Download', false)
            ->assertDontSee('Queue Item 11', false);

        $this->actingAs($this->admin)
            ->get('/admin/import?status=processing')
            ->assertOk()
            ->assertSee('Nenhuma galeria com este filtro.', false);
    }

    public function test_queue_processing_returns_the_current_filter_and_page(): void
    {
        EhImportQueueItem::create([
            'gid' => 9200,
            'token' => 'aaaabbbbcc',
            'title' => 'Finished Page Item',
            'source_url' => 'https://e-hentai.org/g/9200/aaaabbbbcc/',
            'status' => EhImportQueueItem::STATUS_DONE,
            'position' => 1,
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/import/queue/process?status=done&sort=oldest')
            ->assertOk()
            ->assertJsonPath('done', true)
            ->assertJsonPath('queue.filter', 'done')
            ->assertJsonPath('queue.sort', 'oldest')
            ->assertJsonPath('queue.filtered', 1)
            ->assertJsonPath('queue.items.0.title', 'Finished Page Item');
    }

    public function test_queue_processing_truncates_gallery_titles_longer_than_the_comics_column(): void
    {
        $comicId = null;

        try {
            $this->actingAs($this->admin)
                ->post('/admin/import/queue', ['items' => ['4242:aaaabbbbcc']])
                ->assertSessionHas('success');

            $response = $this->actingAs($this->admin)
                ->postJson('/admin/import/queue/process')
                ->assertOk()
                ->assertJsonPath('queue.done', 1);

            $comicId = (int) $response->json('comic.id');
            $comic = Comic::findOrFail($comicId);

            $this->assertSame(255, mb_strlen($comic->title));
            $this->assertStringStartsWith('Very Long Gallery Title', $comic->title);
            $this->assertLessThanOrEqual(255, mb_strlen($comic->slug));
            $this->assertSame(EhImportQueueItem::STATUS_DONE, EhImportQueueItem::firstOrFail()->status);
        } finally {
            if ($comicId !== null) {
                $this->deleteComicTree($comicId);
            }
        }
    }

    public function test_guest_and_non_admin_cannot_use_search_or_the_queue(): void
    {
        $this->get('/admin/import/search?q=furry')->assertRedirect('/login');
        $this->post('/admin/import/queue/process')->assertRedirect('/login');

        $user = User::factory()->create(['admin_level' => 0]);

        $this->actingAs($user)->get('/admin/import/search?q=furry')->assertRedirect('/');
        $this->actingAs($user)->post('/admin/import/queue/process')->assertRedirect('/');
    }

    protected function searchHtml(): string
    {
        return <<<'HTML'
<p>Found about 42 results.</p>
<script>
var prevurl="";
var nexturl="https://e-hentai.org/?f_search=furry&next=999";
</script>
<table class="itg gltc">
<tr>
<td class="gl1c glcat"><div class="cn ct2">Doujinshi</div></td>
<td class="gl2c"><div class="glthumb"><img src="https://img.test/thumb-123.jpg" /></div><div>12 pages</div></td>
<td class="gl3c glname"><a href="https://e-hentai.org/g/123/abcdef0123/"><div class="glink">Cat Furry One</div><div class="gt" title="language:portuguese">portuguese</div></a></td>
<td class="gl4c"><div><a href="https://e-hentai.org/uploader/up123">up123</a></div><div>2026-10-19 22:04</div></td>
</tr>
<tr>
<td class="gl1c glcat"><div class="cn ct4">Manga</div></td>
<td class="gl2c"><div class="glthumb"><img data-src="https://img.test/thumb-456.jpg" src="data:image/gif;base64,R0lGOD" /></div><div>30 pages</div></td>
<td class="gl3c glname"><a href="https://e-hentai.org/g/456/aaaabbbbcc/"><div class="glink">Dog Furry Two</div></a></td>
<td class="gl4c"><div><a href="https://e-hentai.org/uploader/up456">up456</a></div><div>2026-10-18 10:00</div></td>
</tr>
</table>
HTML;
    }

    protected function deleteComicTree(int $comicId): void
    {
        Tag::query()->whereDoesntHave('comics')->delete();
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
