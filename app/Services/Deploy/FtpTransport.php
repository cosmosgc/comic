<?php

namespace App\Services\Deploy;

/**
 * Minimal FTP(S) abstraction. Paths are absolute from the FTP login root,
 * e.g. `/public_html/artisan`. Implemented by CurlFtpTransport (cURL,
 * no ext-ftp needed) and faked in tests.
 */
interface FtpTransport
{
    /**
     * @return list<array{name: string, type: string, size: int, mtime: int|null}>
     *   type is `file` or `dir`. `mtime` is a unix timestamp or null if unknown.
     *
     * @throws DeployException
     */
    public function listDir(string $path): array;

    /**
     * @throws DeployException
     */
    public function read(string $path): string;

    /**
     * Upload a local file to the remote path (creates parent dirs).
     *
     * @throws DeployException
     */
    public function write(string $localFile, string $remotePath): void;

    /**
     * Create a remote directory (including parents). No-op if it exists.
     *
     * @throws DeployException
     */
    public function mkdir(string $path): void;
}
