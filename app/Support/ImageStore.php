<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

/**
 * Stores user-uploaded images under `public/storage` with generated names.
 *
 * The site keeps user media in `public/storage/...` — a real directory on
 * the shared host, not a symlink to `storage/app/public` — so this writes
 * there directly instead of using the `public` disk. Client file names are
 * never trusted: the stored name is random and the extension comes from the
 * detected MIME type, which blocks path traversal and `.php` upload tricks.
 */
class ImageStore
{
    /** Extensions we are willing to write, keyed by the detected MIME type. */
    protected const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Move an upload into `public/storage/$directory` and return its path
     * relative to `public/` (e.g. `storage/avatars/9f3c….jpg`).
     */
    public static function put(UploadedFile $file, string $directory): string
    {
        return 'storage/'.self::putInStorage($file, $directory);
    }

    /**
     * Same as put(), but returns the path relative to `storage/`
     * (e.g. `comics/12/pages/9f3c….jpg`) — the convention comics use.
     */
    public static function putInStorage(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        $target = public_path('storage/'.$directory);

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            throw new RuntimeException("Não foi possível criar a pasta {$target}.");
        }

        $name = bin2hex(random_bytes(12)).'.'.self::extension($file);
        $file->move($target, $name);

        return $directory.'/'.$name;
    }

    /**
     * Delete a stored file, accepting either convention
     * (`storage/avatars/x.jpg` or `comics/1/x.jpg`).
     */
    public static function delete(?string $path): void
    {
        if ($path === null || trim($path) === '') {
            return;
        }

        try {
            $relative = ltrim((string) preg_replace('#^/?storage/#', '', $path), '/');
            $full = public_path('storage/'.$relative);

            if (is_file($full)) {
                @unlink($full);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Extension derived from the detected MIME type — never from the
     * client-supplied filename.
     */
    protected static function extension(UploadedFile $file): string
    {
        return self::EXTENSIONS[(string) $file->getMimeType()] ?? 'jpg';
    }
}
