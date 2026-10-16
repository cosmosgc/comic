<?php

namespace App\Providers;

use App\Models\EhImportSetting;
use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use App\View\Composers\SidebarComposer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
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
        View::composer(
            ['components.left-panel', 'components.right-panel'],
            SidebarComposer::class
        );

        $this->overlayImportSettings();
    }

    /**
     * Import tuning edited in the admin view overlays the file config.
     * Skipped silently on fresh/unmigrated databases.
     */
    protected function overlayImportSettings(): void
    {
        try {
            if (! Schema::hasTable('eh_import_settings')) {
                return;
            }
            $rows = DB::table('eh_import_settings')->pluck('value', 'key')->all();
            foreach (EhImportSetting::definitions() as $key => $def) {
                if (array_key_exists($key, $rows)) {
                    config(["ehimport.{$key}" => EhImportSetting::castValue($key, $rows[$key])]);
                }
            }
        } catch (\Throwable) {
            // Booting (e.g. migrate) without the table: keep file defaults.
        }
    }
}
