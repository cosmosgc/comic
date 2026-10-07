<?php

namespace Tests\Unit;

use App\Services\Deploy\CurlFtpTransport;
use App\Services\Deploy\DeployCancelled;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use Tests\Support\FakeFtpTransport;
use Tests\TestCase;

class DeploySafetyTest extends TestCase
{
    protected string $tmp = '';

    /** @var list<string> */
    protected array $tmpDirs = [];

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            $this->deleteDir($this->tmp);
        }
        foreach ($this->tmpDirs as $dir) {
            if (is_dir($dir)) {
                $this->deleteDir($dir);
            }
        }
        $this->tmpDirs = [];
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

    public function test_verify_never_requests_backslash_paths(): void
    {
        // Regression: PHP dirname() returns `\` for root-level paths on
        // Windows, which URL-encodes to broken FTP paths like `/%5C/`.
        $ftp = $this->seedValidRemote();

        $this->deployer()->verify($ftp);

        $this->assertNotEmpty($ftp->listed);
        foreach ($ftp->listed as $path) {
            $this->assertStringNotContainsString('\\', $path, "Backslash in FTP path: {$path}");
            $this->assertStringNotContainsString('%5C', $path, "Encoded backslash in FTP path: {$path}");
        }
    }

    public function test_verify_suggests_public_dir_candidate(): void
    {
        // Host keeps the web root in public_html instead of public/.
        $ftp = new FakeFtpTransport;
        $ftp->seedFile('/composer.json', json_encode(['name' => 'laravel/laravel']));
        $ftp->seedFile('/artisan', '#!/usr/bin/env php artisan');
        $ftp->seedFile('/bootstrap/app.php', '<?php // app');
        $ftp->seedFile('/vendor/autoload.php', '<?php // autoload');
        $ftp->seedFile('/public_html/index.php', '<?php require bootstrap/autoload');

        $config = config('deploy');
        $config['root'] = '/';
        $config['public_dir'] = 'public';
        $deployer = new FtpDeployer(new ProjectVerifier, $this->localProject(), $config);

        $result = $deployer->verify($ftp);

        $this->assertFalse($result['ok']);
        $this->assertContains('public_html', $result['root_entries']);
        $failed = array_filter($result['checks'], fn ($c) => ! $c['ok']);
        $details = implode(' ', array_column($failed, 'detail'));
        $this->assertStringContainsString('public_html', $details);
    }

    public function test_sync_reuses_precomputed_plan_without_rewalking(): void
    {
        $ftp = $this->seedValidRemote();
        $deployer = $this->deployer();

        $plan = $deployer->plan($ftp);
        $this->assertGreaterThan(0, count($plan['uploads']));
        $listedAfterPlan = count($ftp->listed);

        $result = $deployer->sync($ftp, false, false, null, $plan);

        $this->assertTrue($result['ok']);
        $this->assertSame(count($plan['uploads']), $result['uploaded']);
        // Only the verification listings may add traffic (root + a few
        // marker/public dirs) — never a second full tree walk.
        $this->assertLessThanOrEqual(6, count($ftp->listed) - $listedAfterPlan);
    }

    public function test_sync_reports_progress_per_file(): void
    {
        $ftp = $this->seedValidRemote();
        $deployer = $this->deployer();

        $seen = [];
        $totals = [];
        $result = $deployer->sync(
            $ftp, false, false, null, null,
            function (int $done, int $total) use (&$seen, &$totals) {
                $seen[] = $done;
                $totals[] = $total;
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($seen);
        $this->assertSame($result['uploaded'], end($seen));
        $this->assertTrue(count(array_unique($totals)) === 1, 'Total must stay constant.');
        $sorted = $seen;
        sort($sorted);
        $this->assertSame($sorted, $seen, 'Progress must increase monotonically.');
    }

    public function test_plan_reports_listing_progress(): void
    {
        $ftp = $this->seedValidRemote();

        $heartbeats = [];
        $this->deployer()->plan($ftp, false, function (int $dirs) use (&$heartbeats) {
            $heartbeats[] = $dirs;
        });

        $this->assertNotEmpty($heartbeats);
        $sorted = $heartbeats;
        sort($sorted);
        $this->assertSame($sorted, $heartbeats, 'Dir count must increase monotonically.');
    }

    public function test_plan_aborts_immediately_when_cancel_requested(): void
    {
        $ftp = $this->seedValidRemote();

        try {
            $this->deployer()->plan($ftp, false, null, fn () => true);
            $this->fail('Expected DeployCancelled.');
        } catch (DeployCancelled $e) {
            $this->assertSame([], $ftp->writes);
            $this->assertSame([], $ftp->listed, 'No remote traffic after instant cancel.');
        }
    }

    public function test_sync_stops_uploading_when_cancel_requested(): void
    {
        $ftp = $this->seedValidRemote();
        // Force local files to differ so there is plenty to upload.
        // (public/index.php keeps a bootstrap reference so verification passes.)
        $ftp->seedFile('/bootstrap/app.php', 'stale');
        $ftp->seedFile('/vendor/autoload.php', 'stale');
        $ftp->seedFile('/public/index.php', '<?php // bootstrap entry');
        $calls = 0;

        // Precomputed plan: cancellation must hit the upload loop (planning
        // already finished), leaving earlier files uploaded for resume.
        $plan = $this->deployer()->plan($ftp);
        $this->assertGreaterThanOrEqual(3, count($plan['uploads']));

        try {
            $this->deployer()->sync(
                $ftp, false, false, null, $plan, null,
                function () use (&$calls): bool {
                    $calls++;

                    return $calls > 2;
                }
            );
            $this->fail('Expected DeployCancelled.');
        } catch (DeployCancelled $e) {
            // Earlier files stay uploaded — re-running resumes incrementally.
            $this->assertCount(2, $ftp->writes);
        }
    }

    public function test_vendor_skipped_unless_requested(): void
    {
        $ftp = $this->seedValidRemote();

        $without = $this->deployer()->plan($ftp, false, null, null, false);

        $this->assertGreaterThan(0, $without['vendor_skipped']);
        $this->assertFalse($without['vendor_forced']);
        foreach ($without['uploads'] as $item) {
            $this->assertStringNotContainsString('/vendor/', $item['remote']);
        }

        $with = $this->deployer()->plan($ftp, false, null, null, true);
        $this->assertSame(0, $with['vendor_skipped']);
    }

    public function test_vendor_auto_included_on_fresh_host(): void
    {
        // Remote project without vendor/ yet: skipping it would break the app.
        $ftp = new FakeFtpTransport;
        $ftp->seedFile('/composer.json', json_encode(['name' => 'laravel/laravel']));
        $ftp->seedFile('/artisan', '#!/usr/bin/env php artisan');
        $ftp->seedFile('/bootstrap/app.php', '<?php // app');
        $ftp->seedFile('/public/index.php', '<?php require bootstrap/autoload');

        $plan = $this->deployer()->plan($ftp, false, null, null, false);

        $this->assertTrue($plan['vendor_forced']);
        $this->assertSame(0, $plan['vendor_skipped']);
        $vendorUploads = array_filter(
            $plan['uploads'],
            fn ($item) => str_contains($item['remote'], '/vendor/')
        );
        $this->assertNotEmpty($vendorUploads);
    }

    public function test_curl_transport_reuses_and_resets_shared_handle(): void
    {
        if (! function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl missing.');
        }
        $transport = new CurlFtpTransport([
            'host' => 'example.invalid',
            'username' => 'u',
            'password' => 'p',
        ]);
        $ref = new \ReflectionClass($transport);
        $shared = $ref->getMethod('sharedHandle');
        $shared->setAccessible(true);
        $reset = $ref->getMethod('resetSharedHandle');
        $reset->setAccessible(true);
        $prop = $ref->getProperty('sharedHandle');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($transport));
        $first = $shared->invoke($transport);
        $this->assertSame($first, $shared->invoke($transport), 'Handle must be reused.');
        $reset->invoke($transport);
        $this->assertNull($prop->getValue($transport));
        $this->assertNotSame($first, $shared->invoke($transport), 'Reset must drop the handle.');
    }

    public function test_quick_mode_only_considers_recently_changed_files(): void
    {
        $ftp = $this->seedValidRemote();
        $root = $this->localProject();
        $old = time() - 7200;
        foreach (['composer.json', 'artisan', 'bootstrap/app.php', 'vendor/autoload.php', 'public/index.php', 'app/Models/Comic.php'] as $rel) {
            touch($root.'/'.$rel, $old);
        }
        // One file changed after the last success.
        file_put_contents($root.'/app/Models/Comic.php', '<?php // comic model v2');
        $successFile = $root.'/storage/app/deploy/last-success.json';
        if (! is_dir(dirname($successFile))) {
            mkdir(dirname($successFile), 0777, true);
        }
        file_put_contents($successFile, json_encode(['timestamp' => time() - 3600, 'uploaded' => 1]));

        $plan = $this->deployer()->plan($ftp, false, null, null, true, true);

        $this->assertTrue($plan['quick']);
        $this->assertGreaterThan(0, $plan['quick_skipped']);
        $remotes = array_column($plan['uploads'], 'remote');
        $this->assertContains('/app/Models/Comic.php', $remotes);
        foreach ($remotes as $remote) {
            $this->assertSame('/app/Models/Comic.php', $remote, 'Only the changed file may upload.');
        }
    }

    public function test_quick_mode_without_baseline_falls_back_to_full(): void
    {
        $ftp = $this->seedValidRemote();

        $plan = $this->deployer()->plan($ftp, false, null, null, true, true);

        $this->assertFalse($plan['quick']);
        $this->assertGreaterThan(0, count($plan['uploads']));
    }

    public function test_record_success_anchors_later_quick_runs(): void
    {
        $deployer = $this->deployer();

        $this->assertNull($deployer->lastSuccessAt());
        $deployer->recordSuccess(3);

        $this->assertNotNull($deployer->lastSuccessAt());
        $this->assertEqualsWithDelta(time(), $deployer->lastSuccessAt(), 5);
    }

    public function test_sync_reports_current_file_via_on_file_hook(): void
    {
        $ftp = $this->seedValidRemote();
        $plan = $this->deployer()->plan($ftp);
        $this->assertGreaterThan(0, count($plan['uploads']));

        $seen = [];
        $result = $this->deployer()->sync(
            $ftp, false, false, null, $plan, null, null, true, false,
            function (string $remote, int $doneSoFar, int $total) use (&$seen) {
                $seen[] = [$remote, $doneSoFar, $total];
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertCount(count($plan['uploads']), $seen);
        // doneSoFar starts at 0 and total stays constant.
        $this->assertSame(0, $seen[0][1]);
        $this->assertSame(count($plan['uploads']), $seen[0][2]);
        $this->assertSame(
            array_column($plan['uploads'], 'remote'),
            array_column($seen, 0),
            'Hook must fire for every upload in plan order.'
        );
    }

    public function test_curl_transport_references_only_defined_constants(): void
    {
        // The fake transport masks typos like CURLOPT_FTP_CREATE_DIRS
        // (correct: CURLOPT_FTP_CREATE_MISSING_DIRS) — misspelled constants
        // only blow up on a live run, so assert them statically here.
        if (! function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl missing.');
        }
        $source = (string) file_get_contents(app_path('Services/Deploy/CurlFtpTransport.php'));
        preg_match_all('/\b(CURLOPT_[A-Z_]+|CURLFTP_[A-Z_]+|CURLUSESSL_[A-Z_]+)\b/', $source, $matches);
        $constants = array_unique($matches[1]);
        $this->assertNotEmpty($constants);
        foreach ($constants as $constant) {
            $this->assertTrue(defined($constant), "Undefined curl constant referenced: {$constant}");
        }
    }

    public function test_build_assets_skipped_unless_requested(): void
    {
        $root = sys_get_temp_dir().'/deploy-build-test-'.uniqid();
        mkdir($root.'/public/build/assets', 0777, true);
        mkdir($root.'/app', 0777, true);
        file_put_contents($root.'/public/build/assets/app.css', 'css');
        file_put_contents($root.'/app/Code.php', '<?php');
        file_put_contents($root.'/composer.json', json_encode(['name' => 'laravel/laravel']));
        $this->tmpDirs[] = $root;

        $config = config('deploy');
        $config['root'] = '/';
        $ftp = $this->seedValidRemote();

        $deployer = new FtpDeployer(new ProjectVerifier, $root, $config);
        $without = $deployer->plan($ftp, false, null, null, true, false, false);

        $this->assertGreaterThan(0, $without['build_skipped']);
        foreach ($without['uploads'] as $item) {
            $this->assertStringNotContainsString('public/build', $item['remote']);
        }

        $with = $deployer->plan($ftp, false, null, null, true, false, true);
        $this->assertSame(0, $with['build_skipped']);
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
