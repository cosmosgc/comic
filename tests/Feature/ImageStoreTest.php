<?php

namespace Tests\Feature;

use App\Support\ImageStore;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageStoreTest extends TestCase
{
    public function test_put_creates_missing_upload_directories(): void
    {
        $directory = 'testing-'.bin2hex(random_bytes(4));
        $target = public_path('storage/'.$directory);

        $this->assertDirectoryDoesNotExist($target);

        $path = ImageStore::put(UploadedFile::fake()->image('banner.jpg', 40, 40), $directory);

        $this->assertDirectoryExists($target);
        $this->assertStringStartsWith('storage/'.$directory.'/', $path);
        $this->assertFileExists(public_path($path));

        @unlink(public_path($path));
        @rmdir($target);
    }
}
