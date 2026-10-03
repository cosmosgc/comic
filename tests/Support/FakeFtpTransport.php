<?php

namespace Tests\Support;

use App\Services\Deploy\DeployException;
use App\Services\Deploy\FtpTransport;

/**
 * In-memory FTP server for deploy tests. Paths are absolute from `/`.
 */
class FakeFtpTransport implements FtpTransport
{
    /** @var array<string, array{type: string, content: string}> */
    protected array $nodes = [];

    /** @var list<string> */
    public array $writes = [];

    public function seedFile(string $path, string $content = 'x'): void
    {
        $path = $this->normalize($path);
        $this->mkdir(dirname($path));
        $this->nodes[$path] = ['type' => 'file', 'content' => $content];
    }

    public function listDir(string $path): array
    {
        $path = $this->normalize($path);
        if ($path !== '/' && ! isset($this->nodes[$path])) {
            throw new DeployException("No such directory: {$path}");
        }
        if ($path !== '/' && $this->nodes[$path]['type'] !== 'dir') {
            throw new DeployException("Not a directory: {$path}");
        }

        $entries = [];
        $prefix = $path === '/' ? '/' : $path.'/';
        $seen = [];
        foreach ($this->nodes as $nodePath => $node) {
            if (! str_starts_with($nodePath, $prefix)) {
                continue;
            }
            $rest = substr($nodePath, strlen($prefix));
            if ($rest === '' || str_contains($rest, '/')) {
                continue;
            }
            $seen[$rest] = $node;
        }
        foreach ($seen as $name => $node) {
            $entries[] = [
                'name' => $name,
                'type' => $node['type'],
                'size' => strlen($node['content']),
                'mtime' => null,
            ];
        }

        return $entries;
    }

    public function read(string $path): string
    {
        $path = $this->normalize($path);
        if (! isset($this->nodes[$path]) || $this->nodes[$path]['type'] !== 'file') {
            throw new DeployException("No such file: {$path}");
        }

        return $this->nodes[$path]['content'];
    }

    public function write(string $localFile, string $remotePath): void
    {
        $remotePath = $this->normalize($remotePath);
        $this->mkdir(dirname($remotePath));
        $this->writes[] = $remotePath;
        $this->nodes[$remotePath] = [
            'type' => 'file',
            'content' => (string) file_get_contents($localFile),
        ];
    }

    public function mkdir(string $path): void
    {
        $path = $this->normalize($path);
        if ($path === '/') {
            return;
        }
        if (isset($this->nodes[$path])) {
            return;
        }
        $parent = dirname($path);
        if ($parent !== $path) {
            $this->mkdir($parent);
        }
        $this->nodes[$path] = ['type' => 'dir', 'content' => ''];
    }

    protected function normalize(string $path): string
    {
        $path = '/'.trim(str_replace('\\', '/', $path), '/');

        return $path === '' ? '/' : $path;
    }
}
