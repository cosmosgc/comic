<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Uploads must never trust the client's filename and must not leave
 * orphaned files behind when a file is replaced (issues.md #6).
 */
class UploadSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_comic_pages_are_stored_with_generated_names(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/comics', [
            'title' => 'Upload Naming Test',
            // Hostile names: the stored name must ignore both.
            'images' => [
                UploadedFile::fake()->image('minha página.jpg', 60, 80),
                UploadedFile::fake()->image('../../evil.jpg', 60, 80),
            ],
        ]);

        $response->assertOk();
        $comicId = (int) $response->json('comic_id');
        $comic = Comic::findOrFail($comicId);

        $pages = $comic->pages()->orderBy('page_number')->get();
        $this->assertCount(2, $pages);

        foreach ($pages as $page) {
            $this->assertMatchesRegularExpression(
                '#^comics/'.$comicId.'/[a-f0-9]{24}\.(jpg|png|gif|webp)$#',
                $page->image_path
            );
            $this->assertStringNotContainsString('..', $page->image_path);
            $this->assertFileExists(public_path('storage/'.$page->image_path));
        }

        // Cover = first page, also with a generated name.
        $this->assertSame($pages->first()->image_path, $comic->image_path);

        $this->cleanupComicFiles($comic);
        $comic->pages()->delete();
        $comic->delete();
    }

    /**
     * Remove only the files this test created, then the now-empty
     * folders. Deleting the whole `comics/{id}` tree is unsafe here:
     * ids restart per test and may match real content on dev machines.
     */
    protected function cleanupComicFiles(Comic $comic): void
    {
        foreach ($comic->pages()->pluck('image_path') as $path) {
            @unlink(public_path('storage/'.$path));
        }

        if ($comic->image_path) {
            @unlink(public_path('storage/'.$comic->image_path));
        }

        @rmdir(public_path("storage/comics/{$comic->id}/pages"));
        @rmdir(public_path("storage/comics/{$comic->id}"));
    }

    public function test_double_extension_uploads_are_rejected(): void
    {
        $user = User::factory()->create();

        // `evil.php` is not an image MIME, so validation stops it before
        // anything touches the disk (defense in depth on top of the
        // generated names ImageStore writes).
        $this->actingAs($user)
            ->post('/comics', [
                'title' => 'Double Extension',
                'images' => [UploadedFile::fake()->image('evil.php', 40, 40)],
            ])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseMissing('comics', ['title' => 'Double Extension']);
    }

    public function test_single_page_upload_uses_a_generated_name(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->post("/comics/{$comic->id}/pages", [
            'image' => UploadedFile::fake()->image('página 01.jpg', 50, 50),
        ])->assertRedirect(route('comics.showById', $comic->id));

        $page = Page::where('comic_id', $comic->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '#^comics/'.$comic->id.'/pages/[a-f0-9]{24}\.(jpg|png|gif|webp)$#',
            $page->image_path
        );
        $this->assertFileExists(public_path('storage/'.$page->image_path));

        $this->cleanupComicFiles($comic);
    }

    public function test_page_upload_rejects_non_images(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/comics/{$comic->id}/pages", [
                'image' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Page::where('comic_id', $comic->id)->count());
    }

    public function test_replacing_an_avatar_deletes_the_previous_file(): void
    {
        $user = User::factory()->create();

        $oldRelative = 'storage/avatars/old-avatar.jpg';
        File::ensureDirectoryExists(public_path('storage/avatars'));
        file_put_contents(public_path($oldRelative), 'old-bytes');
        $user->forceFill(['avatar_image_path' => $oldRelative])->save();

        $this->actingAs($user)->post('/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_image' => UploadedFile::fake()->image('novo avatar.jpg', 40, 40),
        ])->assertRedirect(route('profile.show'));

        $fresh = $user->fresh();
        $this->assertNotSame($oldRelative, $fresh->avatar_image_path);
        $this->assertMatchesRegularExpression(
            '#^storage/avatars/[a-f0-9]{24}\.(jpg|png|gif|webp)$#',
            (string) $fresh->avatar_image_path
        );
        $this->assertFileDoesNotExist(public_path($oldRelative));
        $this->assertFileExists(public_path($fresh->avatar_image_path));

        @unlink(public_path($fresh->avatar_image_path));
    }

    public function test_replacing_and_removing_a_banner_deletes_files(): void
    {
        $user = User::factory()->create();

        $oldRelative = 'storage/banners/old-banner.jpg';
        File::ensureDirectoryExists(public_path('storage/banners'));
        file_put_contents(public_path($oldRelative), 'old-bytes');
        $user->forceFill(['banner_image_path' => $oldRelative])->save();

        $this->actingAs($user)->post('/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'banner_image' => UploadedFile::fake()->image('novo banner.jpg', 200, 60),
        ])->assertRedirect(route('profile.show'));

        $replaced = $user->fresh()->banner_image_path;
        $this->assertNotSame($oldRelative, $replaced);
        $this->assertFileDoesNotExist(public_path($oldRelative));
        $this->assertFileExists(public_path($replaced));

        // Removing it also deletes the file on disk.
        $this->actingAs($user)->post('/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_banner' => '1',
        ])->assertRedirect(route('profile.show'));

        $this->assertNull($user->fresh()->banner_image_path);
        $this->assertFileDoesNotExist(public_path($replaced));
    }

    public function test_post_media_is_stored_with_generated_names(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', [
            'text' => 'Mídia com nome gerado',
            'media' => [UploadedFile::fake()->image('foto do celular.jpg', 30, 30)],
        ])->assertRedirect();

        $post = Post::where('author_id', $user->id)->firstOrFail();
        $this->assertCount(1, $post->media);
        $media = $post->media[0];
        $this->assertMatchesRegularExpression(
            '#^storage/posts_media/[a-f0-9]{24}\.(jpg|png|gif|webp)$#',
            $media
        );
        $this->assertFileExists(public_path($media));

        @unlink(public_path($media));
    }

    public function test_post_media_rejects_non_images_and_too_many_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', [
            'text' => 'Arquivo que não é imagem',
            'media' => [UploadedFile::fake()->create('clip.mp4', 5, 'video/mp4')],
        ])->assertSessionHasErrors('media.0');

        $this->actingAs($user)->post('/posts', [
            'text' => 'Muitas imagens',
            'media' => collect(range(1, 5))
                ->map(fn (int $i) => UploadedFile::fake()->image("f{$i}.jpg", 20, 20))
                ->all(),
        ])->assertSessionHasErrors('media');
    }
}
