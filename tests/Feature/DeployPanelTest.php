<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DeployPanelTest extends TestCase
{
    /** @var list<string> */
    protected array $runFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->runFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    protected function admin(): User
    {
        $admin = User::where('admin_level', '>=', 1)->first();
        if ($admin === null) {
            $this->markTestSkipped('No admin user in database.');
        }

        return $admin;
    }

    protected function writeRun(array $status): string
    {
        $id = bin2hex(random_bytes(16));
        $dir = storage_path('app/deploy');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir.'/'.$id.'.json';
        file_put_contents($path, json_encode(array_merge([
            'id' => $id,
            'state' => 'running',
            'phase' => 'uploading',
            'done' => 3,
            'total' => 10,
            'message' => null,
            'log' => [],
            'updated_at' => now()->toIso8601String(),
        ], $status, ['id' => $id])));
        $this->runFiles[] = $path;

        return $id;
    }

    public function test_deploy_page_shows_progress_stats_elements(): void
    {
        $id = $this->writeRun([]);

        $response = $this->actingAs($this->admin())->withSession([
            // Pretend a run is active so the live progress card renders.
            'deploy_run_id' => $id,
        ])->get('/admin/deploy');

        $response->assertStatus(200);
        foreach ([
            'deploy-phase', 'deploy-progress',
            'stat-sent', 'stat-total', 'stat-remaining',
            'stat-elapsed', 'stat-rate', 'stat-eta',
            'deploy-current', 'deploy-counts', 'deploy-message', 'deploy-log',
            'deploy-cancel',
        ] as $idAttr) {
            $response->assertSee('id="'.$idAttr.'"', false);
        }
    }

    public function test_active_run_resumes_on_reload_without_session_flash(): void
    {
        $id = $this->writeRun(['state' => 'running', 'phase' => 'uploading']);

        // No flashed deploy_run_id: plain reload must still show progress,
        // via the persistent last-run session key.
        $response = $this->actingAs($this->admin())
            ->withSession(['deploy_last_run_id' => $id])
            ->get('/admin/deploy');

        $response->assertStatus(200);
        $response->assertSee('id="deploy-progress"', false);
        $response->assertSee('id="stat-sent"', false);
    }

    public function test_finished_run_shows_static_result_without_polling(): void
    {
        $id = $this->writeRun([
            'state' => 'done',
            'phase' => 'done',
            'message' => 'Update complete: 5 file(s) uploaded.',
            'done' => 5,
            'total' => 5,
            'log' => ['Done: 5 uploaded, 0 unchanged.'],
        ]);

        // Flashed id wins over any other run file that may exist.
        $response = $this->actingAs($this->admin())
            ->withSession(['deploy_run_id' => $id])
            ->get('/admin/deploy');

        $response->assertStatus(200);
        $response->assertSee('Update complete: 5 file(s) uploaded.');
        $response->assertSee('id="deploy-result-log"', false);
        $response->assertDontSee('setInterval', false);
    }
}
