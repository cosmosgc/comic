<?php

namespace App\Services\Deploy;

/**
 * Incremental FTPS code sync: local project -> remote host.
 *
 * Safety rules (non-negotiable):
 *  - ProjectVerifier must pass on the remote root or nothing is uploaded.
 *  - Excluded paths (notably .env) are never uploaded, even if forced.
 *  - Nothing is ever deleted on the remote host (uploads only).
 *  - A file is uploaded when missing remotely or when its size differs
 *    (FTP mtimes are unreliable across servers, so size is the signal;
 *    use `force` to re-upload everything).
 */
class FtpDeployer
{
    /**
     * Always excluded, even if removed from config (user content + secrets).
     */
    public const SAFETY_EXCLUDES = [
        '.env',
        'public/storage/',
    ];

    public function __construct(
        protected ProjectVerifier $verifier,
        protected string $localRoot,
        protected array $config,
    ) {
        //
    }

    public static function fromConfig(ProjectVerifier $verifier, string $localRoot): self
    {
        return new self($verifier, rtrim($localRoot, '/\\'), config('deploy'));
    }

    public function root(): string
    {
        $root = '/'.trim((string) ($this->config['root'] ?? '/'), '/');

        return $root === '/' ? '' : $root;
    }

    public function isConfigured(): bool
    {
        return (string) ($this->config['host'] ?? '') !== ''
            && (string) ($this->config['username'] ?? '') !== '';
    }

    /** @return list<string> missing field labels */
    public function missingConfig(): array
    {
        $missing = [];
        if ((string) ($this->config['host'] ?? '') === '') {
            $missing[] = 'FTP_HOST';
        }
        if ((string) ($this->config['username'] ?? '') === '') {
            $missing[] = 'FTP_USERNAME';
        }
        if (! isset($this->config['password']) || (string) $this->config['password'] === '') {
            $missing[] = 'FTP_PASSWORD';
        }

        return $missing;
    }

    /**
     * @return array{ok: bool, checks: list<array{label: string, ok: bool, detail: string}>, remote_composer_name: string|null}
     */
    public function verify(FtpTransport $ftp): array
    {
        return $this->verifier->verify(
            $ftp,
            $this->root() === '' ? '/' : $this->root(),
            (string) ($this->config['public_dir'] ?? 'public'),
            $this->config['markers'] ?? [],
            $this->localComposerName(),
        );
    }

    /**
     * Build the upload plan without transferring anything.
     *
     * @param  callable(int $dirsListed): void|null  $onProgress  heartbeat per listed dir.
     * @param  callable(): bool|null  $shouldStop  cancel hook (checked per dir).
     * @param  bool  $includeVendor  false to skip vendor/ (it rarely changes).
     * @param  bool  $quick  only consider files changed since the last success.
     * @param  bool  $includeBuild  false to skip public/build/ (compiled assets).
     * @return array{uploads: list<array{local: string, remote: string, size: int}>, skipped: int, excluded: int, vendor_skipped: int, vendor_forced: bool, quick: bool, quick_skipped: int, since: int|null, build_skipped: int}
     */
    public function plan(FtpTransport $ftp, bool $force = false, ?callable $onProgress = null, ?callable $shouldStop = null, bool $includeVendor = true, bool $quick = false, bool $includeBuild = true): array
    {
        // Quick mode narrows candidates to files changed since the last
        // successful sync. Without a baseline it degrades to a full walk.
        $since = $quick ? $this->lastSuccessAt() : null;
        $quickActive = $quick && $since !== null;

        // Filter first so the remote walk only covers dirs we may upload to
        // (never the whole account home when FTP_ROOT is `/`).
        $included = [];
        $vendorFiles = [];
        $excluded = 0;
        $quickSkipped = 0;
        $buildSkipped = 0;
        foreach ($this->localFiles() as $relative => $absolute) {
            if ($this->isExcluded($relative)) {
                $excluded++;

                continue;
            }
            if (! $includeVendor && $this->isVendorPath($relative)) {
                $vendorFiles[$relative] = $absolute;

                continue;
            }
            if (! $includeBuild && $this->isBuildPath($relative)) {
                $buildSkipped++;

                continue;
            }
            if ($quickActive && filemtime($absolute) !== false && filemtime($absolute) <= $since) {
                $quickSkipped++;

                continue;
            }
            $included[$relative] = $absolute;
        }

        // Fresh-host guard: a host without vendor/ is a broken app, so vendor
        // is pulled back in automatically (single cheap listing to check).
        $vendorForced = false;
        if (! $includeVendor && $vendorFiles !== [] && ! $this->remoteHasVendor($ftp)) {
            $included += $vendorFiles;
            $vendorFiles = [];
            $vendorForced = true;
        }

        $remoteIndex = $this->remoteIndex($ftp, array_keys($included), $onProgress, $shouldStop);
        $uploads = [];
        $skipped = 0;

        foreach ($included as $relative => $absolute) {
            $remote = $this->remotePath($relative);
            $size = filesize($absolute);
            $remoteEntry = $remoteIndex[$remote] ?? null;

            if ($force || $remoteEntry === null || ($remoteEntry['size'] ?? -1) !== $size) {
                $uploads[] = ['local' => $absolute, 'remote' => $remote, 'size' => $size];
            } else {
                $skipped++;
            }
        }

        return [
            'uploads' => $uploads,
            'skipped' => $skipped,
            'excluded' => $excluded,
            'vendor_skipped' => count($vendorFiles),
            'vendor_forced' => $vendorForced,
            'quick' => $quickActive,
            'quick_skipped' => $quickSkipped,
            'build_skipped' => $buildSkipped,
            'since' => $since,
        ];
    }

    /**
     * Verify, then upload. Aborts before any transfer when verification fails.
     *
     * @param  callable(string): void|null  $log
     * @param  array{uploads: list<array{local: string, remote: string, size: int}>, skipped: int, excluded: int}|null  $plan
     *                                                                                                                         Precomputed plan (avoids walking the remote tree twice).
     * @param  callable(int $done, int $total): void|null  $progress  called per uploaded file.
     * @param  callable(): bool|null  $shouldStop  cancel hook (checked per file).
     * @param  bool  $includeVendor  false to skip vendor/ (fresh hosts still get it).
     * @param  bool  $quick  only consider files changed since the last success.
     * @param  callable(string $remote, int $doneSoFar, int $total): void|null  $onFile  called before each upload.
     * @param  bool  $includeBuild  false to skip public/build/ (compiled assets).
     * @return array{ok: bool, message: string, uploaded: int, skipped: int, log: list<string>}
     */
    public function sync(FtpTransport $ftp, bool $force = false, bool $dryRun = false, ?callable $log = null, ?array $plan = null, ?callable $progress = null, ?callable $shouldStop = null, bool $includeVendor = true, bool $quick = false, ?callable $onFile = null, bool $includeBuild = true): array
    {
        $lines = [];
        $emit = function (string $line) use (&$lines, $log) {
            $lines[] = $line;
            if ($log !== null) {
                $log($line);
            }
        };

        $verification = $this->verify($ftp);
        if (! $verification['ok']) {
            $emit('ABORTED: remote directory failed Laravel project verification.');
            foreach ($verification['checks'] as $check) {
                if (! $check['ok']) {
                    $emit(" - [FAIL] {$check['label']}: {$check['detail']}");
                }
            }

            return [
                'ok' => false,
                'message' => 'Remote directory is not this Laravel project — nothing was uploaded.',
                'uploaded' => 0,
                'skipped' => 0,
                'log' => $this->capLog($lines),
            ];
        }
        $emit('Verification passed: remote directory is this Laravel project.');

        $plan ??= $this->plan($ftp, $force, null, $shouldStop, $includeVendor, $quick, $includeBuild);
        if ($plan['quick'] ?? false) {
            $since = isset($plan['since']) && $plan['since']
                ? date('Y-m-d H:i', $plan['since'])
                : 'unknown time';
            $emit("Quick mode: only files changed since {$since} are considered (".($plan['quick_skipped'] ?? 0).' older files skipped).');
        } elseif ($quick) {
            $emit('Quick mode requested but no previous success found — full walk instead.');
        }
        if ($plan['vendor_forced'] ?? false) {
            $emit('Remote has no vendor/ yet (fresh host) — including it automatically.');
        }
        $emit(
            count($plan['uploads']).' file(s) to upload, '.$plan['skipped'].' unchanged, '.$plan['excluded'].' excluded.'
            .(($plan['vendor_skipped'] ?? 0) > 0 ? ' '.($plan['vendor_skipped'] ?? 0).' vendor skipped.' : '')
            .(($plan['build_skipped'] ?? 0) > 0 ? ' '.($plan['build_skipped'] ?? 0).' build skipped.' : '')
        );

        if ($dryRun) {
            foreach (array_slice($plan['uploads'], 0, 50) as $item) {
                $emit('would upload: '.ltrim($item['remote'], '/'));
            }
            if (count($plan['uploads']) > 50) {
                $emit('... and '.(count($plan['uploads']) - 50).' more.');
            }

            return [
                'ok' => true,
                'message' => 'Dry run — nothing was uploaded.',
                'uploaded' => 0,
                'skipped' => $plan['skipped'],
                'log' => $this->capLog($lines),
            ];
        }

        $uploaded = 0;
        $total = count($plan['uploads']);
        foreach ($plan['uploads'] as $item) {
            // Outside the try below: cancellation must bubble up, not be
            // misreported as an upload failure (DeployCancelled extends
            // DeployException).
            if ($shouldStop !== null && $shouldStop()) {
                throw new DeployCancelled("Cancelled by operator after {$uploaded} file(s). Re-run to resume.");
            }
            if ($onFile !== null) {
                $onFile($item['remote'], $uploaded, $total);
            }
            try {
                $ftp->write($item['local'], $item['remote']);
                $uploaded++;
                if ($progress !== null) {
                    $progress($uploaded, $total);
                }
                if ($uploaded <= 50 || $uploaded % 100 === 0) {
                    $emit('uploaded: '.ltrim($item['remote'], '/'));
                }
            } catch (DeployException $e) {
                $emit('FAILED: '.ltrim($item['remote'], '/').' — '.$e->getMessage());

                return [
                    'ok' => false,
                    'message' => "Upload failed at {$item['remote']} after {$uploaded} file(s). Fix the error and re-run (sync is incremental).",
                    'uploaded' => $uploaded,
                    'skipped' => $plan['skipped'],
                    'log' => $this->capLog($lines),
                ];
            }
        }
        $emit("Done: {$uploaded} uploaded, {$plan['skipped']} unchanged.");

        return [
            'ok' => true,
            'message' => "Update complete: {$uploaded} file(s) uploaded.",
            'uploaded' => $uploaded,
            'skipped' => $plan['skipped'],
            'log' => $this->capLog($lines),
        ];
    }

    /**
     * Map of remote path => entry, listing ONLY the directories that can
     * receive uploads (parents of included local files). A full recursive
     * walk would crawl the whole account home when FTP_ROOT is `/` and
     * outlast the web request timeout.
     *
     * @param  list<string>  $relatives  included local paths (forward slashes)
     * @param  callable(int $dirsListed): void|null  $onProgress
     * @param  callable(): bool|null  $shouldStop
     * @return array<string, array{name: string, type: string, size: int, mtime: int|null}>
     */
    protected function remoteIndex(FtpTransport $ftp, array $relatives, ?callable $onProgress = null, ?callable $shouldStop = null): array
    {
        $root = $this->root() === '' ? '/' : $this->root();
        $base = rtrim($root, '/');
        $dirs = [$root => true];
        foreach ($relatives as $relative) {
            $dir = $this->parentDir($base.'/'.ltrim($relative, '/'));
            while (true) {
                $dirs[$dir] = true;
                if ($dir === $root) {
                    break;
                }
                $dir = $this->parentDir($dir);
            }
        }

        $index = [];
        $listed = 0;
        foreach (array_keys($dirs) as $dir) {
            if ($shouldStop !== null && $shouldStop()) {
                throw new DeployCancelled('Cancelled by operator.');
            }
            try {
                $entries = $ftp->listDir($dir);
            } catch (DeployException) {
                // Missing remote dir: its files simply count as missing.
                continue;
            } finally {
                $listed++;
                if ($onProgress !== null) {
                    $onProgress($listed);
                }
            }
            foreach ($entries as $entry) {
                $index[rtrim($dir, '/').'/'.$entry['name']] = $entry;
            }
        }

        return $index;
    }

    protected function isVendorPath(string $relative): bool
    {
        return $relative === 'vendor' || str_starts_with($relative, 'vendor/');
    }

    protected function isBuildPath(string $relative): bool
    {
        return $relative === 'public/build' || str_starts_with($relative, 'public/build/');
    }

    /**
     * Single cheap listing: does the remote project already have vendor/?
     */
    protected function remoteHasVendor(FtpTransport $ftp): bool
    {
        $root = $this->root() === '' ? '/' : $this->root();
        try {
            foreach ($ftp->listDir(rtrim($root, '/').'/vendor') as $entry) {
                if ($entry['name'] === 'autoload.php' && $entry['type'] === 'file') {
                    return true;
                }
            }
        } catch (DeployException) {
            return false;
        }

        return false;
    }

    /**
     * Forward-slash parent dir (PHP dirname() returns `\` for root-level
     * paths on Windows, which breaks FTP paths).
     */
    protected function parentDir(string $path): string
    {
        $pos = strrpos(rtrim($path, '/'), '/');

        if ($pos === false || $pos === 0) {
            return '/';
        }

        return substr($path, 0, $pos);
    }

    /**
     * @return array<string, string> relative path (forward slashes) => absolute path
     */
    protected function localFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->localRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            // Never follow symlinks (e.g. a storage:link `public/storage`
            // pointing at storage/app/public) — user content must stay local.
            if ($file->isLink()) {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = substr($absolute, strlen($this->localRoot) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $files[$relative] = $absolute;
        }
        ksort($files);

        return $files;
    }

    protected function remotePath(string $relative): string
    {
        return $this->root().'/'.$relative;
    }

    protected function isExcluded(string $relative): bool
    {
        foreach (array_merge(self::SAFETY_EXCLUDES, $this->config['excludes'] ?? []) as $pattern) {
            $pattern = str_replace('\\', '/', $pattern);
            if (str_ends_with($pattern, '/')) {
                // Directory subtree.
                if ($relative === rtrim($pattern, '/') || str_starts_with($relative, $pattern)) {
                    return true;
                }

                continue;
            }
            if (str_contains($pattern, '*')) {
                if (fnmatch($pattern, $relative)) {
                    return true;
                }

                continue;
            }
            if ($relative === $pattern) {
                return true;
            }
        }

        return false;
    }

    protected function localComposerName(): string
    {
        $path = $this->localRoot.'/composer.json';
        if (! is_file($path)) {
            return '';
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? (string) ($decoded['name'] ?? '') : '';
    }

    public function lastSuccessPath(): string
    {
        return $this->localRoot.'/storage/app/deploy/last-success.json';
    }

    /**
     * Unix timestamp of the last successful real (non-dry) sync, if any.
     */
    public function lastSuccessAt(): ?int
    {
        $path = $this->lastSuccessPath();
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        $ts = is_array($decoded) ? (int) ($decoded['timestamp'] ?? 0) : 0;

        return $ts > 0 ? $ts : null;
    }

    public function recordSuccess(int $uploaded): void
    {
        $path = $this->lastSuccessPath();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode([
            'timestamp' => time(),
            'uploaded' => $uploaded,
        ]));
    }

    /** @param list<string> $lines @return list<string> */
    protected function capLog(array $lines): array
    {
        $max = (int) ($this->config['max_log_lines'] ?? 300);

        return array_slice($lines, -$max);
    }
}
