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
     * @return array{uploads: list<array{local: string, remote: string, size: int}>, skipped: int, excluded: int}
     */
    public function plan(FtpTransport $ftp, bool $force = false): array
    {
        $remoteIndex = $this->remoteIndex($ftp);
        $uploads = [];
        $skipped = 0;
        $excluded = 0;

        foreach ($this->localFiles() as $relative => $absolute) {
            if ($this->isExcluded($relative)) {
                $excluded++;
                continue;
            }
            $remote = $this->remotePath($relative);
            $size = filesize($absolute);
            $remoteEntry = $remoteIndex[$remote] ?? null;

            if ($force || $remoteEntry === null || ($remoteEntry['size'] ?? -1) !== $size) {
                $uploads[] = ['local' => $absolute, 'remote' => $remote, 'size' => $size];
            } else {
                $skipped++;
            }
        }

        return ['uploads' => $uploads, 'skipped' => $skipped, 'excluded' => $excluded];
    }

    /**
     * Verify, then upload. Aborts before any transfer when verification fails.
     *
     * @param callable(string): void|null $log
     *
     * @return array{ok: bool, message: string, uploaded: int, skipped: int, log: list<string>}
     */
    public function sync(FtpTransport $ftp, bool $force = false, bool $dryRun = false, ?callable $log = null): array
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

        $plan = $this->plan($ftp, $force);
        $emit(count($plan['uploads']).' file(s) to upload, '.$plan['skipped'].' unchanged, '.$plan['excluded'].' excluded.');

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
        foreach ($plan['uploads'] as $item) {
            try {
                $ftp->write($item['local'], $item['remote']);
                $uploaded++;
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
     * Map of remote path => entry for every file under the project root.
     * Directories are listed lazily and cached per run.
     *
     * @return array<string, array{name: string, type: string, size: int, mtime: int|null}>
     */
    protected function remoteIndex(FtpTransport $ftp): array
    {
        $index = [];
        $dirs = [$this->root() === '' ? '/' : $this->root()];

        while ($dirs !== []) {
            $dir = array_pop($dirs);
            try {
                $entries = $ftp->listDir($dir);
            } catch (DeployException) {
                continue;
            }
            foreach ($entries as $entry) {
                $full = rtrim($dir, '/').'/'.$entry['name'];
                $index[$full] = $entry;
                if ($entry['type'] === 'dir') {
                    $dirs[] = $full;
                }
            }
        }

        return $index;
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

    /** @param list<string> $lines @return list<string> */
    protected function capLog(array $lines): array
    {
        $max = (int) ($this->config['max_log_lines'] ?? 300);

        return array_slice($lines, -$max);
    }
}
