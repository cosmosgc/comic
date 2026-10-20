<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum attachments used to be written to the `public` disk
     * (storage/app/public), but the site serves user media from the real
     * `public/storage` directory — there is no symlink on the shared host,
     * so those uploads 404'd. Move the already uploaded files next to the
     * rest of the user media.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_attachments')) {
            return;
        }

        $paths = DB::table('forum_attachments')->pluck('path')->filter();

        foreach ($paths as $path) {
            $relative = ltrim((string) $path, '/');
            $source = storage_path('app/public/'.$relative);
            $target = public_path('storage/'.$relative);

            if (! is_file($source) || is_file($target)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target), 0755, true);

            if (! @rename($source, $target)) {
                @copy($source, $target);
            }
        }
    }

    public function down(): void
    {
        // The files stay in public/storage; moving them back would break
        // the URLs the new code generates.
    }
};
