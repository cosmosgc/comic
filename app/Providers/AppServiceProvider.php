<?php

namespace App\Providers;

use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FtpDeployer::class, function () {
            return FtpDeployer::fromConfig(
                new ProjectVerifier,
                base_path()
            );
        });

        $this->app->singleton(ChangelogReader::class, function () {
            return ChangelogReader::fromDefaultPath();
        });

        $this->app->singleton(ChangelogWriter::class, function () {
            return ChangelogWriter::fromDefaultPath();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
