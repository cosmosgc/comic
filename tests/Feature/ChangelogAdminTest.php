<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChangelogReader;
use App\Services\ChangelogWriter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChangelogAdminTest extends TestCase
{
    protected string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');

        // Redirect file storage to temp (never the real changelogs/).
        $this->dir = sys_get_temp_dir().'/changelog-admin-'.uniqid();
        mkdir($this->dir, 0777, true);
        $dir = $this->dir;
        $this->app->singleton(ChangelogReader::class, fn () => new ChangelogReader($dir));
        $this->app->singleton(ChangelogWriter::class, fn () => new ChangelogWriter($dir));

        Http::fake([
            'api.github.com/*' => Http::response([
                [
                    'number' => 99,
                    'title' => 'Shiny PR',
                    'body' => 'Shiny body.',
                    'state' => 'open',
                    'merged_at' => null,
                    'user' => ['login' => 'octo'],
                    'html_url' => 'https://github.com/o/r/pull/99',
                    'created_at' => '2026-10-02T10:00:00Z',
                ],
            ], 200),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir.'/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    protected function admin(): User
    {
        return User::factory()->create(['admin_level' => 1]);
    }

    public function test_manager_lists_missing_prs(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/changelogs');

        $response->assertOk();
        $response->assertSee('Shiny PR');
        $response->assertSee('Import &amp; edit', false);
    }

    public function test_non_admin_is_redirected(): void
    {
        $user = User::factory()->create(['admin_level' => 0]);

        $this->actingAs($user)->get('/admin/changelogs')->assertRedirect('/');
    }

    public function test_import_creates_draft_and_redirects_to_edit(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/changelogs/import', [
            'number' => 99,
        ]);

        $files = glob($this->dir.'/*.json') ?: [];
        $this->assertCount(1, $files);
        $saved = json_decode((string) file_get_contents($files[0]), true);
        $this->assertSame(99, $saved['pr']);
        $this->assertSame('Shiny PR', $saved['title']);

        $id = basename($files[0], '.json');
        $response->assertRedirect(route('admin.changelogs.edit', $id));
    }

    public function test_import_duplicate_is_refused(): void
    {
        file_put_contents(
            $this->dir.'/2026-10-04-existing.json',
            json_encode([
                'pr' => 99, 'title' => 'Existing', 'date' => '2026-10-04',
                'category' => 'Added', 'tags' => [], 'summary' => '', 'body' => '',
            ])
        );

        $response = $this->actingAs($this->admin())->post('/admin/changelogs/import', [
            'number' => 99,
        ]);

        $response->assertRedirect(route('admin.changelogs'));
        $response->assertSessionHas('error');
    }

    public function test_create_and_update_roundtrip(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/changelogs', [
            'title' => 'Handmade entry',
            'date' => '2026-10-04',
            'category' => 'Fixed',
            'pr' => '',
            'tags' => 'a, b',
            'summary' => 'S.',
            'body' => 'B.',
        ])->assertRedirect();

        $files = glob($this->dir.'/*.json') ?: [];
        $this->assertCount(1, $files);
        $id = basename($files[0], '.json');

        $this->actingAs($admin)->get("/admin/changelogs/{$id}/edit")->assertOk();

        $this->actingAs($admin)->put("/admin/changelogs/{$id}", [
            'title' => 'Renamed entry',
            'date' => '2026-10-04',
            'category' => 'Fixed',
            'pr' => '',
            'tags' => '',
            'summary' => '',
            'body' => '',
        ])->assertRedirect(route('admin.changelogs.edit', $id));

        $saved = json_decode((string) file_get_contents($files[0]), true);
        $this->assertSame('Renamed entry', $saved['title']);
    }

    public function test_delete_removes_entry(): void
    {
        $admin = $this->admin();
        file_put_contents(
            $this->dir.'/2026-10-04-doomed.json',
            json_encode([
                'pr' => null, 'title' => 'Doomed', 'date' => '2026-10-04',
                'category' => 'Added', 'tags' => [], 'summary' => '', 'body' => '',
            ])
        );

        $this->actingAs($admin)
            ->delete('/admin/changelogs/2026-10-04-doomed')
            ->assertRedirect(route('admin.changelogs'));

        $this->assertSame([], glob($this->dir.'/*.json') ?: []);
    }
}
