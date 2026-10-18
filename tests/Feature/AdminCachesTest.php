<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Admin → Migrações → "Limpar caches". Compiled Blade files live only on the
 * host (storage/framework is excluded from the FTP deploy), so a deploy that
 * removes a package with Blade extensions leaves views calling dead classes
 * and the site answers 500 until that cache is cleared — which needed console
 * access before this panel action.
 */
class AdminCachesTest extends TestCase
{
    protected string $viewsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');

        // Isolated compiled-view directory: never touches the real cache.
        $this->viewsPath = storage_path('framework/testing/views-clear');
        File::ensureDirectoryExists($this->viewsPath);
        config(['view.compiled' => $this->viewsPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->viewsPath);

        parent::tearDown();
    }

    public function test_admin_clears_caches_and_compiled_views_are_deleted(): void
    {
        $stale = $this->viewsPath.'/0e8c1a2b3c4d5e6f.php';
        File::put($stale, '<?php // compiled while Livewire was installed');
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)
            ->post('/admin/caches/clear')
            ->assertRedirect('/admin/migrations')
            ->assertSessionHas('success');

        $this->assertFileDoesNotExist($stale);
    }

    public function test_clearing_caches_reports_the_command_that_failed(): void
    {
        config(['view.compiled' => null]);
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)
            ->post('/admin/caches/clear')
            ->assertRedirect('/admin/migrations')
            ->assertSessionHas('error', 'Falhou: view:clear');
    }
}
