<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ComicUploadLimitsTest extends TestCase
{
    public function test_oversized_page_image_is_rejected_with_422(): void
    {
        // 1 MB cap for this test; the fake file is 2 MB.
        // Validation runs before any DB write, so this is DB-safe.
        Config::set('upload.max_file_mb', 1);

        $response = $this->postJson('/comics', [
            'title' => 'Too Big Comic',
            'images' => [
                UploadedFile::fake()->image('page01.jpg')->size(2048),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('images.0');
    }

    public function test_store_returns_comic_id_for_sequential_uploads(): void
    {
        // The upload page chains page appends off this id, so the contract matters.
        // comics.store requires a logged-in author (user_id NOT NULL).
        $user = \App\Models\User::first();
        if ($user === null) {
            $this->markTestSkipped('No users in database.');
        }
        $this->actingAs($user);
        Config::set('upload.max_file_mb', 10);

        $response = $this->postJson('/comics', [
            'title' => 'Sequential '.uniqid(),
            'images' => [
                UploadedFile::fake()->image('page01.jpg')->size(100),
            ],
        ]);

        $response->assertStatus(200);
        $comicId = $response->json('comic_id');
        $this->assertNotEmpty($comicId);

        try {
            // Appending a page to the created comic must work (used for pages 2..N).
            $append = $this->postJson("/comics/{$comicId}/pages", [
                'image' => UploadedFile::fake()->image('page02.jpg')->size(100),
            ]);
            $this->assertContains($append->getStatusCode(), [200, 302]);
            $this->assertSame(2, Page::where('comic_id', $comicId)->count());
        } finally {
            $this->deleteComicTree((int) $comicId);
        }
    }

    protected function deleteComicTree(int $comicId): void
    {
        Page::where('comic_id', $comicId)->delete();
        Comic::where('id', $comicId)->delete();
        \Illuminate\Support\Facades\File::deleteDirectory(public_path("storage/comics/{$comicId}"));
    }

    public function test_page_image_within_limit_passes_validation(): void
    {
        // Same rule string the controller uses, exercised directly so no
        // comic is created and no files are moved.
        Config::set('upload.max_file_mb', 10);
        $maxKb = config('upload.max_file_mb') * 1024;

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['images' => [UploadedFile::fake()->image('page01.jpg')->size(100)]],
            ['images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:'.$maxKb]
        );

        $this->assertTrue($validator->passes());
    }
}
