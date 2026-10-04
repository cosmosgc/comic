<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Deploy\FtpDeployer::class, function () {
            return \App\Services\Deploy\FtpDeployer::fromConfig(
                new \App\Services\Deploy\ProjectVerifier,
                base_path()
            );
        });

        $this->app->singleton(\App\Services\ChangelogReader::class, function () {
            return \App\Services\ChangelogReader::fromDefaultPath();
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
