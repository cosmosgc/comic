<?php

namespace App\Console\Commands;

use App\Services\Deploy\CurlFtpTransport;
use App\Services\Deploy\DeployCancelled;
use App\Services\Deploy\DeployException;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use Illuminate\Console\Command;

class DeployHost extends Command
{
    protected $signature = 'deploy:host
        {--dry-run : Show what would be uploaded without transferring anything}
        {--verify-only : Only verify the remote directory, upload nothing}
        {--force : Re-upload every file even when sizes match}
        {--yes : Skip the confirmation prompt (used for background runs)}
        {--with-vendor : Include vendor/ (it is skipped by default; fresh hosts get it automatically)}
        {--quick : Only consider files changed since the last success (default when a success exists)}
        {--full : Full walk, ignoring the last success (default on first run)}
        {--status-file= : Write progress JSON to this file (used for background runs)}';

    protected $description = 'Sync this project to the hosting provider over FTPS (no console needed on the host)';

    public function handle(FtpDeployer $deployer): int
    {
        if (! $deployer->isConfigured()) {
            $this->error('FTP is not configured. Missing: '.implode(', ', $deployer->missingConfig()));
            $this->line('Set FTP_HOST / FTP_USERNAME / FTP_PASSWORD (and FTP_ROOT) in .env — see .env.example.');

            return self::FAILURE;
        }

        if ($this->option('status-file')) {
            $this->writeStatus($this->option('status-file'), ['state' => 'running', 'phase' => 'listing'], true);
        }

        try {
            $transport = new CurlFtpTransport(config('deploy'));
        } catch (DeployException $e) {
            if ($this->option('status-file')) {
                $this->writeStatus($this->option('status-file'), [
                    'state' => 'failed',
                    'message' => $e->getMessage(),
                ], true);
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            return $this->runVerify($deployer, $transport);
        }

        $verification = $deployer->verify($transport);
        $this->printChecks($verification['checks']);
        if (! $verification['ok']) {
            if ($this->option('status-file')) {
                $failed = array_filter($verification['checks'], fn ($c) => ! $c['ok']);
                $this->writeStatus($this->option('status-file'), [
                    'state' => 'failed',
                    'message' => 'Verification failed — nothing was uploaded.',
                    'log' => array_map(fn ($c) => "[FAIL] {$c['label']}: {$c['detail']}", array_values($failed)),
                ], true);
            }
            $this->error('Remote directory is not this Laravel project — nothing was uploaded.');

            return self::FAILURE;
        }

        $statusFile = $this->option('status-file');
        $includeVendor = (bool) $this->option('with-vendor');
        // Quick when possible, full on first run or when forced.
        $quick = $this->option('full') ? false : ($this->option('quick') || $deployer->lastSuccessAt() !== null);
        $shouldStop = $statusFile
            ? function () use ($statusFile): bool {
                $current = is_file($statusFile)
                    ? json_decode((string) file_get_contents($statusFile), true)
                    : null;

                return is_array($current) && ($current['cancel_requested'] ?? false) === true;
            }
            : null;
        $heartbeats = 0;
        $plan = $deployer->plan(
            $transport,
            (bool) $this->option('force'),
            function (int $dirs) use ($statusFile, &$heartbeats) {
                $heartbeats++;
                // The listing pass is the slowest phase — heartbeat so the
                // panel (and the run lock) can see the worker is alive.
                if ($statusFile && $heartbeats % 10 === 0) {
                    $this->writeStatus($statusFile, [
                        'message' => "Listing remote files… ({$dirs} dirs)",
                    ]);
                }
            },
            $shouldStop,
            $includeVendor,
            $quick
        );
        $this->info(count($plan['uploads']).' file(s) to upload, '.$plan['skipped'].' unchanged, '.$plan['excluded'].' excluded.');
        if ($statusFile) {
            $this->writeStatus($statusFile, [
                'phase' => 'uploading',
                'total' => count($plan['uploads']),
                'counts' => [
                    'uploads' => count($plan['uploads']),
                    'skipped' => $plan['skipped'],
                    'excluded' => $plan['excluded'],
                    'vendor_skipped' => $plan['vendor_skipped'] ?? 0,
                    'quick_skipped' => $plan['quick_skipped'] ?? 0,
                    'quick' => $plan['quick'] ?? false,
                ],
                'message' => count($plan['uploads']).' file(s) to upload, '.$plan['skipped'].' unchanged.',
            ], true);
        }

        if ($this->option('dry-run')) {
            foreach (array_slice($plan['uploads'], 0, 50) as $item) {
                $this->line('would upload: '.ltrim($item['remote'], '/'));
            }
            if ($statusFile) {
                $this->writeStatus($statusFile, [
                    'state' => 'done',
                    'phase' => 'done',
                    'message' => 'Dry run — nothing was uploaded. '.count($plan['uploads']).' file(s) would upload.',
                    'done' => 0,
                    'log' => array_merge(
                        ['Dry run — nothing was uploaded.'],
                        array_map(fn ($item) => 'would upload: '.ltrim($item['remote'], '/'), array_slice($plan['uploads'], 0, 50))
                    ),
                ], true);
            }

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Upload '.count($plan['uploads']).' file(s) to '.config('deploy.host').'?')) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        set_time_limit(0);

        $writes = 0;
        try {
            $result = $deployer->sync(
                $transport,
                (bool) $this->option('force'),
                (bool) $this->option('dry-run'),
                function (string $line) use ($statusFile, &$writes) {
                    if ($statusFile) {
                        $writes++;
                        // Throttle status writes; the final write happens below.
                        if ($writes % 20 === 0) {
                            $this->writeStatus($statusFile, ['log_tail' => $line]);
                        }
                    }
                },
                $plan,
                function (int $done, int $total) use ($statusFile) {
                    if ($statusFile) {
                        $this->writeStatus($statusFile, ['done' => $done, 'total' => $total]);
                    }
                },
                $shouldStop,
                $includeVendor,
                $quick,
                function (string $remote, int $done, int $total) use ($statusFile) {
                    if ($statusFile) {
                        $this->writeStatus($statusFile, [
                            'phase' => 'uploading',
                            'current' => ltrim($remote, '/'),
                            'done' => $done,
                            'total' => $total,
                        ]);
                    }
                }
            );
        } catch (DeployCancelled $e) {
            if ($statusFile) {
                $this->writeStatus($statusFile, [
                    'state' => 'cancelled',
                    'phase' => 'cancelled',
                    'current' => null,
                    'message' => $e->getMessage(),
                ], true);
            }
            $this->line($e->getMessage());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($statusFile) {
                $this->writeStatus($statusFile, [
                    'state' => 'failed',
                    'phase' => 'failed',
                    'current' => null,
                    'message' => 'Deploy crashed: '.$e->getMessage(),
                ], true);
            }
            $this->error('Deploy crashed: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        if ($statusFile) {
            $this->writeStatus($statusFile, [
                'state' => $result['ok'] ? 'done' : 'failed',
                'phase' => $result['ok'] ? 'done' : 'failed',
                'current' => null,
                'message' => $result['message'],
                'done' => $result['uploaded'],
                'total' => count($plan['uploads']),
                'log' => $result['log'],
            ], true);
        }

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        // Anchor for future quick runs (real syncs only — dry runs change nothing).
        if (! $this->option('dry-run')) {
            $deployer->recordSuccess($result['uploaded']);
        }

        $this->info($result['message']);
        $this->line('Next: open the admin panel and run pending migrations via System → Migrations.');

        return self::SUCCESS;
    }

    protected function writeStatus(string $path, array $patch, bool $final = false): void
    {
        $current = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $current = $decoded;
            }
        }
        if (! $final) {
            // Incremental updates never regress the state machine.
            unset($patch['state'], $patch['message'], $patch['log']);
        }
        $current = array_merge($current, $patch, ['updated_at' => now()->toIso8601String()]);
        @file_put_contents($path, json_encode($current));
    }

    protected function runVerify(FtpDeployer $deployer, CurlFtpTransport $transport): int
    {
        $verification = $deployer->verify($transport);
        $this->printChecks($verification['checks']);

        if (! $verification['ok']) {
            $this->error('Verification FAILED — deploy would be refused.');

            return self::FAILURE;
        }

        $this->info('Verification passed — remote directory is this Laravel project.');

        return self::SUCCESS;
    }

    /** @param list<array{label: string, ok: bool, detail: string}> $checks */
    protected function printChecks(array $checks): void
    {
        foreach ($checks as $check) {
            $icon = $check['ok'] ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line("{$icon} {$check['label']} — {$check['detail']}");
        }
    }
}
