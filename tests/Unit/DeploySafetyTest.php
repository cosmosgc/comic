<?php

namespace Tests\Unit;

use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use Tests\Support\FakeFtpTransport;
use Tests\TestCase;

class DeploySafetyTest extends TestCase
{
    protected string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            $this->deleteDir($this->tmp);
        }
        parent::tearDown();
    }

    public function test_verify_passes_on_valid_project_root(): void
    {
        $ftp = $this->seedValidRemote();

        $result = $this->deployer()->verify($ftp);

        $this->assertTrue($result['ok'], json_encode($result['checks']));
        $this->assertSame('laravel/laravel', $result['remote_composer_name']);
    }

    public function test_verify_fails_when_marker_missing(): void
    {
        $ftp = $this->seedValidRemote();
        // Simulate a wrong directory: artisan missing.
        $ftp = $this->seedRemoteWithout(['artisan']);

        $result = $this->deployer()->verify($ftp);

        $this->assertFalse($result['ok']);
    }

    public function test_verify_fails_on_different_project(): void
    {
        $ftp = $this->seedValidRemote('someone/else');

        $result = $this->deployer()->verify($ftp);

        $this->assertFalse($result['ok']);
    }

    public function test_verify_fails_when_public_index_missing(): void
    {
        // Remote without public/index.php (empty public dir).
        $ftp = new FakeFtpTransport;
        $ftp->seedFile('/composer.json', json_encode(['name' => 'laravel/laravel']));
        $ftp->seedFile('/artisan', '#!/usr/bin/env php artisan');
        $ftp->seedFile('/bootstrap/app.php', '<?php // app');
        $ftp->seedFile('/vendor/autoload.php', '<?php // autoload');
        $ftp->mkdir('/public');

        $result = $this->deployer()->verify($ftp);

        $this->assertFalse($result['ok']);
    }

    public function test_sync_aborts_without_any_upload_when_verification_fails(): void
    {
        $ftp = $this->seedRemoteWithout(['artisan']);

        $result = $this->deployer()->sync($ftp);

        $this->assertFalse($result['ok']);
        $this->assertSame([], $ftp->writes, 'Nothing may be uploaded when verification fails.');
    }

    public function test_sync_uploads_code_but_never_env(): void
    {
        $ftp = $this->seedValidRemote();

        $result = $this->deployer()->sync($ftp);

        $this->assertTrue($result['ok'], implode("\n", $result['log']));
        $this->assertGreaterThan(0, $result['uploaded']);
        foreach ($ftp->writes as $write) {
            $this->assertFalse(
                (bool) preg_match('#(^|/)\.env(\.|$)#', $write),
                "Must never upload env files, got: {$write}"
            );
        }
        // The app code itself must be uploaded.
        $this->assertContains('/app/Models/Comic.php', $ftp->writes);
    }

    public function test_sync_is_incremental(): void
    {
        $ftp = $this->seedValidRemote();
        $deployer = $this->deployer();

        $first = $deployer->sync($ftp);
        $this->assertTrue($first['ok']);
        $this->assertGreaterThan(0, $first['uploaded']);

        $second = $deployer->sync($ftp);
        $this->assertTrue($second['ok']);
        $this->assertSame(0, $second['uploaded'], 'Second run must skip unchanged files.');
    }

    public function test_dry_run_uploads_nothing(): void
    {
        $ftp = $this->seedValidRemote();

        $result = $this->deployer()->sync($ftp, force: false, dryRun: true);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $ftp->writes);
    }

    public function test_sync_never_uploads_public_storage_user_content(): void
    {
        // Local and remote user content differ by design — neither side
        // may overwrite the other.
        $ftp = $this->seedValidRemote();
        $ftp->seedFile('/public/storage/avatars/remote-avatar.jpg', 'remote-bytes');
        $ftp->seedFile('/public/storage/posts_media/remote-clip.mp4', 'remote-bytes');

        $result = $this->deployer()->sync($ftp);

        $this->assertTrue($result['ok']);
        foreach ($ftp->writes as $write) {
            $this->assertStringStartsNotWith(
                '/public/storage/', $write, "User content must never upload, got: {$write}"
            );
        }
        // The remote files are untouched (different content preserved).
        $this->assertSame('remote-bytes', $ftp->read('/public/storage/avatars/remote-avatar.jpg'));
        $this->assertSame('remote-bytes', $ftp->read('/public/storage/posts_media/remote-clip.mp4'));
    }

    public function test_safety_excludes_survive_emptied_config(): void
    {
        $ftp = $this->seedValidRemote();
        $config = config('deploy');
        $config['root'] = '/';
        $config['excludes'] = []; // operator error: wiped the list
        $deployer = new FtpDeployer(new ProjectVerifier, $this->localProject(), $config);

        $result = $deployer->sync($ftp);

        $this->assertTrue($result['ok']);
        foreach ($ftp->writes as $write) {
            $this->assertFalse(
                $write === '/.env' || str_starts_with($write, '/public/storage/'),
                "Safety net failed, uploaded: {$write}"
            );
        }
    }

    public function test_symlinked_dirs_are_not_traversed(): void
    {
        // e.g. a storage:link public/storage pointing at storage/app/public.
        $this->localProject();
        $target = $this->tmp.'/storage/app/public';
        if (! is_dir($target)) {
            mkdir($target, 0777, true);
        }
        file_put_contents($target.'/secret-upload.jpg', 'user-bytes');
        $link = $this->tmp.'/public/storage-link';
        if (! is_dir(dirname($link))) {
            mkdir(dirname($link), 0777, true);
        }
        if (! @symlink($target, $link)) {
            $this->markTestSkipped('Cannot create symlinks on this system.');
        }

        $ftp = $this->seedValidRemote();
        $result = $this->deployer()->sync($ftp);

        $this->assertTrue($result['ok']);
        foreach ($ftp->writes as $write) {
            $this->assertStringNotContainsString('storage-link', $write);
            $this->assertStringNotContainsString('secret-upload', $write);
        }
    }

    // -- helpers ----------------------------------------------------------

    protected function deployer(): FtpDeployer
    {
        $config = config('deploy');
        $config['root'] = '/';

        return new FtpDeployer(new ProjectVerifier, $this->localProject(), $config);
    }

    /** Build a fake local project in a temp dir. */
    protected function localProject(): string
    {
        if ($this->tmp === '') {
            $this->tmp = sys_get_temp_dir().'/deploy-test-'.uniqid();
            $files = [
                'composer.json' => json_encode(['name' => 'laravel/laravel']),
                'artisan' => '#!/usr/bin/env php artisan',
                'bootstrap/app.php' => '<?php // app',
                'vendor/autoload.php' => '<?php // autoload',
                'public/index.php' => '<?php require __DIR__."/../vendor/autoload.php"; $app = require __DIR__."/../bootstrap/app.php";',
                'app/Models/Comic.php' => '<?php // comic model',
                '.env' => 'APP_KEY=secret-local-key',
                'storage/logs/laravel.log' => 'local log noise',
                // Local user content (differs from remote by design).
                'public/storage/avatars/local-avatar.jpg' => 'local-bytes',
                'public/storage/comics/7/pages/local-page.jpg' => 'local-bytes',
                'public/storage/posts_media/local-clip.mp4' => 'local-bytes',
            ];
            foreach ($files as $relative => $content) {
                $absolute = $this->tmp.'/'.$relative;
                if (! is_dir(dirname($absolute))) {
                    mkdir(dirname($absolute), 0777, true);
                }
                file_put_contents($absolute, $content);
            }
        }

        return $this->tmp;
    }

    /** Remote that already matches the local project (sizes may differ). */
    protected function seedValidRemote(string $composerName = 'laravel/laravel'): FakeFtpTransport
    {
        $ftp = new FakeFtpTransport;
        $ftp->seedFile('/composer.json', json_encode(['name' => $composerName]));
        $ftp->seedFile('/artisan', '#!/usr/bin/env php artisan');
        $ftp->seedFile('/bootstrap/app.php', '<?php // app');
        $ftp->seedFile('/vendor/autoload.php', '<?php // autoload');
        $ftp->seedFile('/public/index.php', '<?php require bootstrap/autoload stuff');

        return $ftp;
    }

    /** @param list<string> $missing top-level names to omit, e.g. ['artisan'] */
    protected function seedRemoteWithout(array $missing): FakeFtpTransport
    {
        $ftp = new FakeFtpTransport;
        $all = [
            '/composer.json' => json_encode(['name' => 'laravel/laravel']),
            '/artisan' => '#!/usr/bin/env php artisan',
            '/bootstrap/app.php' => '<?php // app',
            '/vendor/autoload.php' => '<?php // autoload',
            '/public/index.php' => '<?php require bootstrap/autoload stuff',
        ];
        foreach ($all as $path => $content) {
            if (in_array(ltrim($path, '/'), $missing, true)) {
                // Still create the parent dir so the failure is "missing", not "unlistable".
                $ftp->mkdir(dirname($path) === '/' || dirname($path) === '.' ? '/' : dirname($path));
                continue;
            }
            $ftp->seedFile($path, $content);
        }

        return $ftp;
    }

    protected function deleteDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
