<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicTransfer;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Services\ComicTransferService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComicTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        $this->artisan('migrate');

        Config::set('transfer.api_token', 'test-secret');
        Config::set('transfer.remote_url', 'https://remote.test');
        Config::set('transfer.remote_token', 'test-secret');
        Config::set('transfer.chunk', 2);
    }

    public function test_transfer_api_rejects_missing_or_wrong_token(): void
    {
        Config::set('transfer.api_token', 'test-secret');

        $this->postJson('/api/transfer/comics', ['title' => 'Nope'])->assertUnauthorized();

        $this->withToken('wrong')->postJson('/api/transfer/comics', ['title' => 'Nope'])
            ->assertUnauthorized();
    }

    public function test_transfer_api_creates_missing_categories_with_sender_colors(): void
    {
        User::factory()->create(['admin_level' => 1]);
        $existing = TagCategory::create([
            'name' => 'male', 'label' => 'Male',
            'background' => '#111111', 'border' => '#222222', 'text_color' => '#333333',
            'namespaces' => ['male'],
        ]);

        $created = $this->withToken('test-secret')->postJson('/api/transfer/comics', [
            'title' => 'Categorized Transfer',
            'tags' => ['male:dog boy', 'plain tag'],
            'categories' => [
                'male' => [
                    'label' => 'HACKED', 'background' => '#000000', 'border' => 'nope',
                    'text_color' => '#000000', 'namespaces' => ['male'], 'keywords' => [],
                ],
                'newcat' => [
                    'label' => 'New Cat', 'background' => '#123456', 'border' => '#654321',
                    'text_color' => '#abcdef', 'namespaces' => ['newcat'], 'keywords' => ['special'],
                ],
                'BAD NAME!' => ['label' => 'x'],
            ],
        ])->assertOk();
        $comic = Comic::findOrFail($created->json('comic_id'));

        try {
            // Existing category untouched (admin edits win).
            $this->assertSame('#111111', $existing->fresh()->background);
            // Missing category created with exact sender attributes.
            $new = TagCategory::where('name', 'newcat')->firstOrFail();
            $this->assertSame('#123456', $new->background);
            $this->assertSame(['newcat'], $new->namespaces);
            $this->assertSame(['special'], $new->keywords);
            // Invalid names skipped.
            $this->assertNull(TagCategory::where('name', 'bad name!')->first());
            // Namespaced tag resolved + learned like an EH import.
            $tag = Tag::where('name', 'dog boy')->firstOrFail();
            $this->assertSame('male', $tag->category->name);
            $this->assertContains('dog boy', $tag->category->learned_keywords);
            $this->assertTrue($comic->tags->contains($tag));
        } finally {
            $comic->delete();
            $new->delete();
        }
    }

    public function test_transfer_api_creates_pages_and_finalizes(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $created = $this->withToken('test-secret')->postJson('/api/transfer/comics', [
            'title' => 'Remote Comic',
            'author' => 'Author',
            'tags' => ['female:stockings', 'custom'],
        ])->assertOk()->assertJsonStructure(['comic_id']);
        $remoteId = $created->json('comic_id');

        $comic = Comic::findOrFail($remoteId);
        $this->assertSame($admin->id, (int) $comic->user_id);
        $this->assertContains('stockings', $comic->tags->pluck('name')->all());

        try {
            foreach ([1, 2, 3] as $number) {
                $this->withToken('test-secret')->post("/api/transfer/comics/{$remoteId}/pages", [
                    'image' => UploadedFile::fake()->image("p{$number}.jpg", 100, 100)->size(20),
                    'page_number' => $number,
                ])->assertOk()->assertJson(['page_number' => $number]);
            }

            $this->withToken('test-secret')->postJson("/api/transfer/comics/{$remoteId}/finalize")
                ->assertOk()->assertJson(['pages' => 3]);

            $this->assertSame(
                [1, 2, 3],
                $comic->pages()->orderBy('page_number')->pluck('page_number')->all()
            );
            $this->assertSame("comics/{$remoteId}/page-0001.jpg", $comic->fresh()->image_path);
        } finally {
            File::deleteDirectory(public_path("storage/comics/{$remoteId}"));
            Comic::where('id', $remoteId)->delete();
        }
    }

    public function test_local_send_pushes_pages_in_order_then_finalizes(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(3);

        $pushed = [];
        Http::fake([
            'remote.test/*' => function ($request) use (&$pushed) {
                $pushed[] = $request->url();
                if (str_ends_with($request->url(), '/transfer/comics')) {
                    return Http::response(['comic_id' => 99], 200);
                }
                if (str_ends_with($request->url(), '/pages') && $request->method() === 'GET') {
                    return Http::response(['pages' => []], 200);
                }
                if (str_ends_with($request->url(), '/finalize')) {
                    return Http::response(['ok' => true, 'pages' => 3, 'url' => 'https://remote.test/comics/99'], 200);
                }

                return Http::response(['ok' => true, 'page_number' => 1], 200);
            },
        ]);

        try {
            $start = $this->actingAs($admin)->postJson("/admin/comics/{$comic->id}/transfer")
                ->assertOk()->assertJson(['total' => 3]);
            $transfer = ComicTransfer::findOrFail($start->json('transfer_id'));
            $this->assertSame(99, (int) $transfer->remote_comic_id);

            $pushUrl = $start->json('push_url');
            $this->actingAs($admin)->postJson($pushUrl)
                ->assertOk()->assertJson(['done' => 2, 'total' => 3, 'complete' => false]);
            $this->actingAs($admin)->postJson($pushUrl)
                ->assertOk()->assertJson(['done' => 3, 'total' => 3, 'complete' => true])
                ->assertJsonPath('url', 'https://remote.test/comics/99');

            $this->assertTrue($transfer->fresh()->isDone());
            // Remote page list checked per poll; pages went out in
            // order, then finalize last.
            $this->assertSame(
                [
                    'https://remote.test/api/transfer/comics',
                    'https://remote.test/api/transfer/comics/99/pages',
                    'https://remote.test/api/transfer/comics/99/pages',
                    'https://remote.test/api/transfer/comics/99/pages',
                    'https://remote.test/api/transfer/comics/99/pages',
                    'https://remote.test/api/transfer/comics/99/pages',
                    'https://remote.test/api/transfer/comics/99/finalize',
                ],
                $pushed
            );
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_remote_pages_endpoint_lists_numbers(): void
    {
        $comic = Comic::factory()->create();
        $comic->pages()->create(['image_path' => 'x', 'page_number' => 2]);
        $comic->pages()->create(['image_path' => 'y', 'page_number' => 1]);

        try {
            $this->getJson("/api/transfer/comics/{$comic->id}/pages")->assertUnauthorized();
            $this->withToken('test-secret')->getJson("/api/transfer/comics/{$comic->id}/pages")
                ->assertOk()->assertJson(['pages' => [1, 2]]);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_sender_payload_carries_namespaces_and_categories(): void
    {
        User::factory()->create(['admin_level' => 1]);
        TagCategory::create([
            'name' => 'male', 'label' => 'Male',
            'background' => '#111111', 'border' => '#222222', 'text_color' => '#333333',
            'namespaces' => ['male'],
        ]);
        $comic = Comic::factory()->create();
        $comic->tags()->attach(Tag::findOrCreateResolved('male:dog boy')->id);

        Http::fake(['remote.test/*' => Http::response(['comic_id' => 7], 200)]);

        try {
            $this->assertSame(7, app(ComicTransferService::class)->createRemote($comic->fresh()));
            Http::assertSent(function ($request) {
                $data = $request->data();

                return $data['tags'] === ['male:dog boy']
                    && ($data['categories']['male']['namespaces'] ?? null) === ['male'];
            });
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_push_skips_pages_already_remote(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(3);

        $pagePosts = 0;
        Http::fake([
            'remote.test/*' => function ($request) use (&$pagePosts) {
                if (str_ends_with($request->url(), '/transfer/comics')) {
                    return Http::response(['comic_id' => 99], 200);
                }
                if (str_ends_with($request->url(), '/pages')) {
                    if ($request->method() === 'GET') {
                        return Http::response(['pages' => [1, 2]], 200);
                    }
                    $pagePosts++;
                }
                if (str_ends_with($request->url(), '/finalize')) {
                    return Http::response(['ok' => true, 'pages' => 3, 'url' => 'https://remote.test/comics/99'], 200);
                }

                return Http::response(['ok' => true, 'page_number' => 1], 200);
            },
        ]);

        try {
            $start = $this->actingAs($admin)->postJson("/admin/comics/{$comic->id}/transfer")->assertOk();
            $this->actingAs($admin)->postJson($start->json('push_url'))
                ->assertOk()->assertJson(['done' => 3, 'total' => 3, 'complete' => true]);

            // Only the missing page was uploaded; the rest skipped.
            $this->assertSame(1, $pagePosts);
            $transfer = ComicTransfer::where('comic_id', $comic->id)->firstOrFail();
            $this->assertSame('https://remote.test/comics/99', $transfer->remote_view_url);

            // Admin list links straight to the remote comic.
            $this->actingAs($admin)->get('/admin/comics')->assertOk()
                ->assertSee('✓ No remoto', false)
                ->assertSee('https://remote.test/comics/99', false);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_local_send_refuses_when_remote_unconfigured(): void
    {
        Config::set('transfer.remote_url', '');
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(1);

        try {
            $this->actingAs($admin)->postJson("/admin/comics/{$comic->id}/transfer")
                ->assertStatus(502);
            $this->assertDatabaseMissing('comic_transfers', ['comic_id' => $comic->id]);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    protected function comicWithFiles(int $count): Comic
    {
        $comic = Comic::factory()->create();
        $dir = public_path("storage/comics/{$comic->id}");
        mkdir($dir, 0755, true);
        $bytes = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
        for ($n = 1; $n <= $count; $n++) {
            $name = 'page-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'.gif';
            file_put_contents($dir.'/'.$name, $bytes);
            $comic->pages()->create(['image_path' => "comics/{$comic->id}/{$name}", 'page_number' => $n]);
        }

        return $comic->fresh();
    }

    protected function deleteComicTree(int $comicId): void
    {
        Tag::query()->whereDoesntHave('comics')->delete();
        ComicTransfer::where('comic_id', $comicId)->delete();
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
