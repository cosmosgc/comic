<?php

namespace App\Services;

use App\Models\Comic;
use App\Models\Page;
use App\Models\Tag;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Local ↔ remote comic transfer as a single .zip: manifest (metadata +
 * tags + page order) plus the image files. Importing creates fresh IDs,
 * so collisions between installations are impossible.
 */
class ComicPackageService
{
    public const MANIFEST = 'comic.json';

    public const MAX_ENTRIES = 2500;

    public const MAX_UNCOMPRESSED_BYTES = 3 * 1024 * 1024 * 1024;

    /**
     * Build a .zip of the comic in a temp file (caller deletes it).
     *
     * @throws ComicPackageException
     */
    public function export(Comic $comic): string
    {
        $pages = $comic->pages()->orderBy('page_number')->get(['image_path', 'page_number']);
        $files = [];
        $skipped = 0;
        foreach ($pages as $page) {
            $full = public_path('storage/'.$page->image_path);
            if (! is_file($full)) {
                $skipped++;

                continue;
            }
            $files[] = ['page' => $page, 'full' => $full, 'name' => basename($page->image_path)];
        }
        if ($files === []) {
            throw new ComicPackageException('Nenhum arquivo de página encontrado para exportar.');
        }

        $zipPath = sys_get_temp_dir().'/comic-export-'.$comic->id.'-'.Str::random(8).'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new ComicPackageException('Não foi possível criar o pacote.');
        }

        try {
            foreach ($files as $file) {
                $zip->addFile($file['full'], 'pages/'.$file['name']);
            }
            $zip->addFromString(self::MANIFEST, json_encode([
                'version' => 1,
                'exported_at' => now()->toIso8601String(),
                'title' => $comic->title,
                'author' => $comic->author,
                'description' => $comic->description,
                'slug' => $comic->slug,
                'published_at' => $comic->published_at?->toIso8601String(),
                'cover' => basename($comic->image_path ?? ''),
                'skipped_missing' => $skipped,
                'tags' => $comic->tags->pluck('name')->values()->all(),
                'pages' => array_map(fn ($f) => $f['name'], $files),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } finally {
            $zip->close();
        }

        return $zipPath;
    }

    /**
     * Rebuild a comic from a package .zip (fresh IDs, tags resolved
     * through the categorizer like any other import).
     *
     * @throws ComicPackageException
     */
    public function import(string $zipPath, int $userId): Comic
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new ComicPackageException('Pacote inválido ou corrompido.');
        }

        $entries = [];
        $totalBytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            $this->assertSafeEntry($name);
            $entries[] = $name;
            $totalBytes += (int) ($stat['size'] ?? 0);
        }
        $zip->close();

        if (count($entries) > self::MAX_ENTRIES || $totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
            throw new ComicPackageException('Pacote grande demais ou com arquivos demais.');
        }
        if (! in_array(self::MANIFEST, $entries, true)) {
            throw new ComicPackageException('Pacote sem manifesto (comic.json).');
        }

        $dir = sys_get_temp_dir().'/comic-import-'.Str::random(12);
        mkdir($dir, 0755, true);
        try {
            $zip = new ZipArchive;
            $zip->open($zipPath);
            $zip->extractTo($dir);
            $zip->close();

            $manifest = json_decode((string) file_get_contents($dir.'/'.self::MANIFEST), true);
            if (! is_array($manifest) || ! isset($manifest['title']) || ! is_array($manifest['pages'] ?? null)) {
                throw new ComicPackageException('Manifesto inválido.');
            }

            $slug = trim((string) ($manifest['slug'] ?? ''));
            if ($slug === '' || Comic::where('slug', $slug)->exists()) {
                $slug = Comic::generateUniqueSlug((string) $manifest['title']);
            }

            $comic = Comic::create([
                'title' => (string) $manifest['title'],
                'author' => $manifest['author'] ?? null,
                'description' => $manifest['description'] ?? null,
                'slug' => $slug,
                'published_at' => ! empty($manifest['published_at']) ? $manifest['published_at'] : null,
                'user_id' => $userId,
            ]);

            $destDir = public_path("storage/comics/{$comic->id}/pages");
            mkdir($destDir, 0755, true);

            $number = 0;
            $cover = null;
            foreach ($manifest['pages'] as $name) {
                $src = $dir.'/pages/'.basename((string) $name);
                if (! is_file($src)) {
                    continue;
                }
                $number++;
                $safe = 'page-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT).'.'.strtolower((string) pathinfo($src, PATHINFO_EXTENSION));
                copy($src, $destDir.'/'.$safe);
                Page::create([
                    'comic_id' => $comic->id,
                    'image_path' => "comics/{$comic->id}/pages/{$safe}",
                    'page_number' => $number,
                ]);
                if ($cover === null || basename((string) $name) === ($manifest['cover'] ?? null)) {
                    $cover = "comics/{$comic->id}/pages/{$safe}";
                }
            }
            if ($number === 0) {
                $comic->delete();
                throw new ComicPackageException('Nenhuma imagem válida dentro do pacote.');
            }

            $tagIds = [];
            foreach ((array) ($manifest['tags'] ?? []) as $tagName) {
                $tagName = trim((string) $tagName);
                if ($tagName !== '') {
                    $tagIds[] = Tag::findOrCreateResolved($tagName)->id;
                }
            }
            $comic->tags()->sync($tagIds);
            $comic->update(['image_path' => $cover ?? $comic->pages()->orderBy('page_number')->first()?->image_path]);

            return $comic->fresh();
        } finally {
            $this->deleteDirectory($dir);
        }
    }

    /**
     * @throws ComicPackageException
     */
    protected function assertSafeEntry(string $name): void
    {
        if ($name === '' || str_starts_with($name, '/') || str_starts_with($name, '\\')
            || str_contains($name, '..') || str_contains($name, "\0")) {
            throw new ComicPackageException('Pacote com caminho suspeito: '.$name);
        }
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
