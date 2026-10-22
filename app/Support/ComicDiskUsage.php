<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Total bytes on disk for each comic folder (cover + pages), used by the
 * admin comics list. Statting tens of thousands of page files on every
 * request is too slow, so the totals are cached in one entry and rebuilt
 * only when page files change — anywhere that creates or deletes page
 * records calls flush() (Page model events cover per-row writes; mass
 * deletes and comic deletions flush explicitly, since cascades bypass the
 * events). A TTL self-heals any drift anyway.
 */
class ComicDiskUsage
{
    protected const CACHE_KEY = 'comics-disk-usage';

    protected const CACHE_TTL_HOURS = 6;

    /**
     * Bytes on disk per comic id, computed lazily for ids missing from
     * the cache and merged back so late additions are not recomputed.
     *
     * @param  list<int>  $comicIds
     * @return array<int, int>
     */
    public static function bytesById(array $comicIds): array
    {
        $bytes = Cache::get(self::CACHE_KEY, []);
        $missed = false;

        foreach ($comicIds as $id) {
            if (! array_key_exists((int) $id, $bytes)) {
                $bytes[(int) $id] = self::directoryBytes((int) $id);
                $missed = true;
            }
        }

        if ($missed) {
            Cache::put(self::CACHE_KEY, $bytes, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $bytes;
    }

    /**
     * Forget the totals; the next admin list re-stats what changed.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sum every file under `public/storage/comics/{id}` (cover + pages).
     */
    protected static function directoryBytes(int $comicId): int
    {
        $directory = public_path("storage/comics/{$comicId}");
        if (! is_dir($directory)) {
            return 0;
        }

        $total = 0;
        foreach (File::allFiles($directory) as $file) {
            try {
                $total += (int) $file->getSize();
            } catch (\RuntimeException) {
                // File vanished between the scan and the stat; keep counting.
            }
        }

        return $total;
    }
}
