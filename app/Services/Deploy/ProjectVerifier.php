<?php

namespace App\Services\Deploy;

/**
 * Safety gate: proves the remote FTP directory really is THIS Laravel
 * project before any file is uploaded. A failed check aborts the update.
 */
class ProjectVerifier
{
    /**
     * @param list<string> $markers  e.g. ['artisan', 'composer.json', 'bootstrap/app.php']
     *
     * @return array{ok: bool, checks: list<array{label: string, ok: bool, detail: string}>, remote_composer_name: string|null, root_entries: list<string>}
     */
    public function verify(FtpTransport $ftp, string $root, string $publicDir, array $markers, string $localComposerName): array
    {
        $root = '/'.trim($root, '/');
        $checks = [];
        $ok = true;

        $fail = function (string $label, string $detail) use (&$checks, &$ok) {
            $checks[] = ['label' => $label, 'ok' => false, 'detail' => $detail];
            $ok = false;
        };
        $pass = function (string $label, string $detail) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => true, 'detail' => $detail];
        };

        // 1. Root must be listable.
        try {
            $rootEntries = $this->entryMap($ftp->listDir($root));
            $pass('Remote directory is reachable', "Listed {$root} (".count($rootEntries).' entries).');
        } catch (DeployException $e) {
            $fail('Remote directory is reachable', "Cannot list {$root}: {$e->getMessage()}");

            return ['ok' => false, 'checks' => $checks, 'remote_composer_name' => null, 'root_entries' => []];
        }

        $rootNames = array_keys($rootEntries);

        // 2. Every marker must exist (nested markers checked via parent listing).
        $listingCache = [$root => $rootEntries];
        foreach ($markers as $marker) {
            $remote = $root.'/'.ltrim($marker, '/');
            $parent = $this->parentDir($remote);
            $base = basename($remote);
            try {
                if (! isset($listingCache[$parent])) {
                    $listingCache[$parent] = $this->entryMap($ftp->listDir($parent));
                }
                if (isset($listingCache[$parent][$base])) {
                    $pass("Marker present: {$marker}", 'Found.');
                } else {
                    $fail("Marker present: {$marker}", 'Not found — wrong directory?');
                }
            } catch (DeployException $e) {
                $fail("Marker present: {$marker}", "Cannot check: {$e->getMessage()}");
            }
        }

        // 3. Remote composer.json must describe the same project.
        $remoteName = null;
        try {
            $decoded = json_decode($ftp->read($root.'/composer.json'), true);
            $remoteName = is_array($decoded) ? ($decoded['name'] ?? null) : null;
            if ($remoteName === null) {
                $fail('Same project (composer.json name)', 'Remote composer.json has no `name`.');
            } elseif ($remoteName === $localComposerName) {
                $pass('Same project (composer.json name)', "`{$remoteName}` matches.");
            } else {
                $fail('Same project (composer.json name)', "Remote is `{$remoteName}`, local is `{$localComposerName}` — refusing to overwrite a different app.");
            }
        } catch (DeployException $e) {
            $fail('Same project (composer.json name)', "Cannot read remote composer.json: {$e->getMessage()}");
        }

        // 4. Public dir must look like the Laravel web root.
        $publicPath = $root.'/'.trim($publicDir, '/');
        try {
            $publicEntries = $this->entryMap($ftp->listDir($publicPath));
            if (! isset($publicEntries['index.php'])) {
                $fail(
                    "Web root has index.php ({$publicDir})",
                    'index.php missing — is FTP_PUBLIC_DIR correct?'.$this->suggestPublicDir($rootNames, $publicDir)
                );
            } else {
                $head = substr($ftp->read($publicPath.'/index.php'), 0, 2000);
                if (str_contains($head, 'bootstrap') || str_contains($head, 'autoload')) {
                    $pass("Web root has index.php ({$publicDir})", 'Looks like the Laravel front controller.');
                } else {
                    $fail("Web root has index.php ({$publicDir})", 'index.php does not reference bootstrap/autoload.');
                }
            }
        } catch (DeployException $e) {
            $fail(
                "Web root reachable ({$publicDir})",
                "Cannot list {$publicPath}: {$e->getMessage()}.".$this->suggestPublicDir($rootNames, $publicDir)
            );
        }

        // 5. Informational: remote .env exists (it is never overwritten).
        try {
            if (isset($listingCache[$root]['.env'])) {
                $pass('Remote .env preserved', '.env exists and is excluded from uploads.');
            } else {
                $pass('Remote .env preserved', '.env not found remotely — the app may not be installed yet; uploads still exclude .env.');
            }
        } catch (\Throwable) {
            // Informational only; never fails the run.
        }

        return [
            'ok' => $ok,
            'checks' => $checks,
            'remote_composer_name' => $remoteName,
            'root_entries' => array_slice($rootNames, 0, 100),
        ];
    }

    /**
     * @param list<string> $rootNames
     */
    protected function suggestPublicDir(array $rootNames, string $configured): string
    {
        $candidates = ['public', 'public_html', 'www', 'htdocs', 'httpdocs', 'web'];
        $found = array_values(array_intersect($candidates, $rootNames));
        // Don't suggest what's already configured.
        $found = array_values(array_diff($found, [trim($configured, '/')]));

        if ($found === []) {
            return ' Top-level folders here: '.implode(', ', array_slice($rootNames, 0, 20));
        }

        return ' Did you mean FTP_PUBLIC_DIR='.implode(' or ', $found).'?';
    }

    /**
     * @param list<array{name: string, type: string, size: int, mtime: int|null}> $entries
     * @return array<string, array{name: string, type: string, size: int, mtime: int|null}>
     */
    protected function entryMap(array $entries): array
    {
        $map = [];
        foreach ($entries as $entry) {
            $map[$entry['name']] = $entry;
        }

        return $map;
    }

    /**
     * Forward-slash parent dir. PHP's dirname() returns a backslash for
     * root-level paths on Windows (e.g. `\` for `/artisan`), which then
     * gets URL-encoded into broken FTP paths like `/%5C/`.
     */
    protected function parentDir(string $path): string
    {
        $pos = strrpos(rtrim($path, '/'), '/');

        if ($pos === false || $pos === 0) {
            return '/';
        }

        return substr($path, 0, $pos);
    }
}
