<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Models\EhImportSetting;
use App\Models\ForumSetting;
use App\Models\GamificationSetting;
use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use App\Services\Deploy\FtpDeployer;
use App\Services\Deploy\ProjectVerifier;
use App\View\Composers\NotificationComposer;
use App\View\Composers\SidebarComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // The Login listener has no EventServiceProvider here (Laravel 12
        // streamlined structure), so it is wired explicitly. Without this,
        // neither the analytics login row nor the activity feed gets one.
        Event::listen(Login::class, LogUserLogin::class);

        View::composer(
            ['components.left-panel', 'components.right-panel'],
            SidebarComposer::class
        );

        View::composer(
            ['layouts.app', 'layouts.forum-layout'],
            NotificationComposer::class
        );

        $this->overlayImportSettings();
        $this->overlayGamificationSettings();
        $this->overlayForumSettings();
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

    /**
     * Gamification tuning edited in Admin → Gamificação overlays the
     * file config. Skipped silently on fresh/unmigrated databases.
     */
    protected function overlayGamificationSettings(): void
    {
        // Never overlay in tests: feature tests boot before switching to the
        // in-memory database, so this would read the (dev) default connection
        // and leak real overrides into the suite. Tests that cover overlaying
        // call GamificationSetting::applyToConfig() explicitly.
        if ($this->app->environment('testing')) {
            return;
        }

        try {
            if (! Schema::hasTable('gamification_settings')) {
                return;
            }
            GamificationSetting::applyToConfig();
        } catch (\Throwable) {
            // Booting (e.g. migrate) without the table: keep file defaults.
        }
    }

    /**
     * Forum tuning edited in Admin → Fórum overlays the file config.
     * Skipped silently on fresh/unmigrated databases.
     */
    protected function overlayForumSettings(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        try {
            if (! Schema::hasTable('forum_settings')) {
                return;
            }
            ForumSetting::applyToConfig();
        } catch (\Throwable) {
            // Booting (e.g. migrate) without the table: keep file defaults.
        }
    }
}
