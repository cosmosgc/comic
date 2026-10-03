<?php

namespace App\Services\Deploy;

/**
 * FTPS/FTP client built on ext-curl (available on virtually every host,
 * unlike ext-ftp). Pure PHP, no extra composer packages.
 */
class CurlFtpTransport implements FtpTransport
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected bool $ssl;
    protected bool $verifySsl;
    protected int $timeout;
    protected bool $freshConnection;

    /** @var resource|\CurlHandle|null shared handle for connection reuse */
    protected $sharedHandle = null;

    /**
     * @param array{host: string, port?: int, username?: string, password?: string, ssl?: bool, verify_ssl?: bool, timeout?: int, fresh_connection?: bool} $config
     *
     * @throws DeployException
     */
    public function __construct(array $config)
    {
        if (! function_exists('curl_init')) {
            throw new DeployException('PHP cURL extension (ext-curl) is required for FTP deployment.');
        }

        $this->host = trim((string) ($config['host'] ?? ''));
        $this->port = (int) ($config['port'] ?? 21);
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->ssl = (bool) ($config['ssl'] ?? true);
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? false);
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->freshConnection = (bool) ($config['fresh_connection'] ?? false);

        if ($this->host === '') {
            throw new DeployException('FTP host is not configured (FTP_HOST).');
        }
    }

    public function __destruct()
    {
        $this->resetSharedHandle();
    }

    public function listDir(string $path): array
    {
        $path = $this->normalizeDir($path);

        // Prefer MLSD (machine-readable). Fall back to Unix LIST output.
        try {
            return $this->parseMlsd($this->request($path, 'MLSD'));
        } catch (DeployException) {
            return $this->parseList($this->request($path, 'LIST -a'));
        }
    }

    public function read(string $path): string
    {
        return $this->request($this->encodePath($path), null);
    }

    public function write(string $localFile, string $remotePath): void
    {
        if (! is_file($localFile)) {
            throw new DeployException("Local file not found: {$localFile}");
        }

        $handle = fopen($localFile, 'rb');
        if ($handle === false) {
            throw new DeployException("Cannot open local file: {$localFile}");
        }

        try {
            $this->perform(
                $this->encodePath($remotePath),
                function ($ch) use ($handle, $localFile) {
                    curl_setopt($ch, CURLOPT_UPLOAD, true);
                    // Create missing remote dirs and retry (value 2; the newer
                    // _ALL alias for this mode isn't defined in PHP).
                    curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, CURLFTP_CREATE_DIR_RETRY);
                    curl_setopt($ch, CURLOPT_INFILE, $handle);
                    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localFile));
                },
                "upload {$remotePath}"
            );
        } finally {
            fclose($handle);
        }
    }

    public function mkdir(string $path): void
    {
        $path = rtrim($path, '/');
        if ($path === '' || $path === '/') {
            return;
        }

        // MKD parents first so nested paths work.
        $segments = explode('/', ltrim($path, '/'));
        $current = '';
        foreach ($segments as $segment) {
            $current .= '/'.$segment;
            $mkd = $current;
            try {
                $this->perform(
                    $this->encodePath('/'),
                    function ($ch) use ($mkd) {
                        curl_setopt($ch, CURLOPT_POSTQUOTE, ["MKD {$mkd}"]);
                        curl_setopt($ch, CURLOPT_NOBODY, true);
                    },
                    "mkdir {$current}"
                );
            } catch (DeployException $e) {
                // 550 "already exists" (or similar) is fine — anything else aborts.
                if (! $this->isAlreadyExistsError($e->getMessage())) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Run a custom FTP command that returns a directory listing body.
     */
    protected function request(string $encodedPath, ?string $command): string
    {
        return $this->perform(
            $encodedPath,
            $command !== null
                ? function ($ch) use ($command) {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $command);
                }
                : null,
            ($command ?? 'GET')." {$encodedPath}"
        );
    }

    /**
     * Run one FTP operation. Reuses a single connection for the whole run
     * (a fresh TLS handshake per op costs ~1s); on any failure the shared
     * connection is dropped and the op retried once on a fresh one.
     *
     * @param callable(resource|\CurlHandle): void|null $configure per-op options
     */
    protected function perform(string $encodedPath, ?callable $configure, string $action): string
    {
        if ($this->freshConnection) {
            $ch = curl_init();
            $this->setupHandle($ch, $encodedPath);
            if ($configure !== null) {
                $configure($ch);
            }
            try {
                return $this->execute($ch, $action);
            } finally {
                $this->closeHandle($ch);
            }
        }

        try {
            $ch = $this->sharedHandle();
            $this->setupHandle($ch, $encodedPath);
            if ($configure !== null) {
                $configure($ch);
            }

            return $this->execute($ch, $action);
        } catch (DeployConnectionException $e) {
            // Possibly a poisoned persistent connection — retry once fresh.
            // Plain FTP reply errors (missing file, …) are NOT retried: the
            // connection is healthy, the answer is just "no".
            $this->resetSharedHandle();
            $ch = $this->sharedHandle();
            $this->setupHandle($ch, $encodedPath);
            if ($configure !== null) {
                $configure($ch);
            }

            return $this->execute($ch, $action);
        }
    }

    /**
     * @return resource|\CurlHandle
     */
    protected function sharedHandle()
    {
        if ($this->sharedHandle === null) {
            $this->sharedHandle = curl_init();
        }

        return $this->sharedHandle;
    }

    protected function resetSharedHandle(): void
    {
        if ($this->sharedHandle !== null) {
            $this->closeHandle($this->sharedHandle);
            $this->sharedHandle = null;
        }
    }

    /**
     * @param resource|\CurlHandle $ch
     */
    protected function closeHandle($ch): void
    {
        try {
            curl_close($ch);
        } catch (\Throwable) {
            // Already closed — nothing to do.
        }
    }

    /**
     * Apply URL + common options. Always called on a fresh or reset handle,
     * so per-op options (UPLOAD, CUSTOMREQUEST, …) can never leak across ops.
     *
     * @param resource|\CurlHandle $ch
     */
    protected function setupHandle($ch, string $encodedPath): void
    {
        curl_reset($ch);
        curl_setopt($ch, CURLOPT_URL, "ftp://{$this->host}:{$this->port}{$encodedPath}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(15, $this->timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($this->ssl) {
            curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
            if (! $this->verifySsl) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
        }
    }

    /**
     * @param resource|\CurlHandle $ch
     */
    protected function execute($ch, string $action): string
    {
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $errno !== 0) {
            throw new DeployConnectionException("FTP {$action} failed: {$error} (curl {$errno})");
        }
        // FTP 4xx/5xx replies surface as codes here for quote commands.
        if ($code >= 400) {
            throw new DeployException("FTP {$action} failed with reply {$code}: {$error}");
        }

        return (string) $body;
    }

    protected function isAlreadyExistsError(string $message): bool
    {
        return (bool) preg_match('/\b(550|exists|exist|duplicate|already)\b/i', $message);
    }

    protected function normalizeDir(string $path): string
    {
        $path = '/'.trim($path, '/');
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        return $this->encodePath($path);
    }

    protected function encodePath(string $path): string
    {
        $segments = explode('/', $path);
        $segments = array_map(fn ($s) => rawurlencode($s), $segments);

        return implode('/', $segments);
    }

    /**
     * @return list<array{name: string, type: string, size: int, mtime: int|null}>
     */
    protected function parseMlsd(string $body): array
    {
        $entries = [];
        foreach (preg_split('/\r\n|\n/', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // `type=file;size=12;modify=20240101120000; name`
            if (! preg_match('/^(.*);\s(.*)$/', $line, $m)) {
                continue;
            }
            $facts = [];
            foreach (explode(';', $m[1]) as $fact) {
                $fact = trim($fact);
                if (str_contains($fact, '=')) {
                    [$k, $v] = explode('=', $fact, 2);
                    $facts[strtolower(trim($k))] = trim($v);
                }
            }
            $name = $m[2];
            $type = strtolower($facts['type'] ?? '');
            if (in_array($type, ['cdir', 'pdir'], true) || in_array($name, ['.', '..'], true)) {
                continue;
            }
            $mtime = null;
            if (! empty($facts['modify']) && preg_match('/^\d{14}(\.\d+)?$/', $facts['modify'])) {
                $parsed = \DateTimeImmutable::createFromFormat('YmdHis', substr($facts['modify'], 0, 14), new \DateTimeZone('UTC'));
                $mtime = $parsed ? $parsed->getTimestamp() : null;
            }
            $entries[] = [
                'name' => $name,
                'type' => $type === 'dir' ? 'dir' : 'file',
                'size' => (int) ($facts['size'] ?? 0),
                'mtime' => $mtime,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{name: string, type: string, size: int, mtime: int|null}>
     */
    protected function parseList(string $body): array
    {
        $entries = [];
        foreach (preg_split('/\r\n|\n/', trim($body)) as $line) {
            $line = rtrim($line);
            // `-rw-r--r-- 1 user group 1234 Jan  2 15:04 file name`
            if (! preg_match('/^([dl-])[rwxst-]{9}\s+\d+\s+\S+\s+\S+\s+(\d+)\s+([A-Z][a-z]{2}\s+\d+\s+[\d:]+)\s+(.+)$/', $line, $m)) {
                continue;
            }
            $name = $m[4];
            // Strip symlink target (`link -> target`).
            if (str_contains($name, ' -> ')) {
                $name = substr($name, 0, strpos($name, ' -> '));
            }
            if (in_array($name, ['.', '..'], true)) {
                continue;
            }
            $entries[] = [
                'name' => $name,
                'type' => $m[1] === 'd' ? 'dir' : 'file',
                'size' => (int) $m[2],
                'mtime' => strtotime($m[3]) ?: null,
            ];
        }

        return $entries;
    }
}
