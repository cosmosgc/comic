<?php

namespace App\Console\Commands;

use App\Services\Deploy\CurlFtpTransport;
use App\Services\Deploy\DeployException;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use Illuminate\Console\Command;

class DeployHost extends Command
{
    protected $signature = 'deploy:host
        {--dry-run : Show what would be uploaded without transferring anything}
        {--verify-only : Only verify the remote directory, upload nothing}
        {--force : Re-upload every file even when sizes match}';

    protected $description = 'Sync this project to the hosting provider over FTPS (no console needed on the host)';

    public function handle(FtpDeployer $deployer): int
    {
        if (! $deployer->isConfigured()) {
            $this->error('FTP is not configured. Missing: '.implode(', ', $deployer->missingConfig()));
            $this->line('Set FTP_HOST / FTP_USERNAME / FTP_PASSWORD (and FTP_ROOT) in .env — see .env.example.');

            return self::FAILURE;
        }

        try {
            $transport = new CurlFtpTransport(config('deploy'));
        } catch (DeployException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('verify-only')) {
            return $this->runVerify($deployer, $transport);
        }

        $verification = $deployer->verify($transport);
        $this->printChecks($verification['checks']);
        if (! $verification['ok']) {
            $this->error('Remote directory is not this Laravel project — nothing was uploaded.');

            return self::FAILURE;
        }

        $plan = $deployer->plan($transport, (bool) $this->option('force'));
        $this->info(count($plan['uploads']).' file(s) to upload, '.$plan['skipped'].' unchanged, '.$plan['excluded'].' excluded.');

        if ($this->option('dry-run')) {
            foreach (array_slice($plan['uploads'], 0, 50) as $item) {
                $this->line('would upload: '.ltrim($item['remote'], '/'));
            }

            return self::SUCCESS;
        }

        if (! $this->confirm('Upload '.count($plan['uploads']).' file(s) to '.config('deploy.host').'?')) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        set_time_limit(0);
        $result = $deployer->sync($transport, (bool) $this->option('force'));
        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->line('Next: open the admin panel and run pending migrations via System → Migrations.');

        return self::SUCCESS;
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
