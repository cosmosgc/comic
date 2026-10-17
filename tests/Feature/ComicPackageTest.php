<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\User;
use App\Services\ComicPackageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ComicPackageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_export_downloads_zip_with_manifest_and_images(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(2);

        try {
            $this->actingAs($admin)->get("/admin/comics/{$comic->id}/package")
                ->assertOk()
                ->assertDownload("comic-{$comic->id}-{$comic->slug}.zip");

            // Contents verified through the service (same code path).
            $zipPath = app(ComicPackageService::class)->export($comic->fresh());
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath));
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();
            unlink($zipPath);

            $this->assertContains('comic.json', $names);
            $this->assertContains('pages/page-0001.gif', $names);
            $this->assertContains('pages/page-0002.gif', $names);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_package_round_trip_rebuilds_comic(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(2, ['title' => 'Round Trip '.uniqid(), 'author' => 'Pack Author']);
        $comic->tags()->attach(Tag::create(['name' => 'pack-tag'])->id);

        try {
            $service = app(ComicPackageService::class);
            $zipPath = $service->export($comic);

            $copy = $service->import($zipPath, $admin->id);
            unlink($zipPath);

            $this->assertNotSame($comic->id, $copy->id);
            $this->assertSame($comic->title, $copy->title);
            $this->assertSame('Pack Author', $copy->author);
            $this->assertSame(['pack-tag'], $copy->tags->pluck('name')->all());
            $this->assertSame([1, 2], $copy->pages()->orderBy('page_number')->pluck('page_number')->all());
            foreach ($copy->pages as $page) {
                $this->assertFileExists(public_path('storage/'.$page->image_path));
            }
            $this->assertNotNull($copy->image_path);

            $this->deleteComicTree($copy->id);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_package_import_via_upload_form(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = $this->comicWithFiles(1, ['title' => 'Upload Pack '.uniqid()]);

        try {
            $zipPath = app(ComicPackageService::class)->export($comic);

            $this->actingAs($admin)->post('/admin/comics/package', [
                'package' => new UploadedFile($zipPath, 'pack.zip', 'application/zip', null, true),
            ])->assertRedirect();
            unlink($zipPath);

            $copy = Comic::where('title', $comic->title)->where('id', '!=', $comic->id)->firstOrFail();
            $this->assertSame(1, $copy->pages()->count());

            $this->deleteComicTree($copy->id);
        } finally {
            $this->deleteComicTree($comic->id);
        }
    }

    public function test_package_import_rejects_garbage(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->post('/admin/comics/package', [
            'package' => UploadedFile::fake()->create('fake.zip', 10, 'application/zip'),
        ])->assertRedirect(route('admin.comics'))
            ->assertSessionHas('error');
    }

    /**
     * Comic with real gif files on disk (1x1 placeholders).
     */
    protected function comicWithFiles(int $count, array $overrides = []): Comic
    {
        $comic = Comic::factory()->create($overrides);
        $dir = public_path("storage/comics/{$comic->id}/pages");
        // ensureDirectoryExists: a leftover folder from another test must
        // not turn this into a "mkdir(): File exists" error.
        File::ensureDirectoryExists($dir);
        $bytes = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==');
        for ($n = 1; $n <= $count; $n++) {
            $name = 'page-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT).'.gif';
            file_put_contents($dir.'/'.$name, $bytes);
            $comic->pages()->create(['image_path' => "comics/{$comic->id}/pages/{$name}", 'page_number' => $n]);
        }
        $comic->update(['image_path' => "comics/{$comic->id}/pages/page-0001.gif"]);

        return $comic->fresh();
    }

    protected function deleteComicTree(int $comicId): void
    {
        Tag::query()->whereDoesntHave('comics')->delete();
        Comic::where('id', $comicId)->delete();
        File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }
}
