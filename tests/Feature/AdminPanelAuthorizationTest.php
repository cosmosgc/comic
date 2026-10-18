<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Regression coverage for issues.md #14: the panels that mutate the
 * host (migrations, FTP deploy, comic import) must stay behind
 * `auth` + AdminMiddleware.
 */
class AdminPanelAuthorizationTest extends TestCase
{
    /** Panels that only render. */
    protected const GET_PANELS = ['/admin/migrations', '/admin/deploy', '/admin/import'];

    /** Endpoints that write to the database or the remote host. */
    protected const WRITE_PANELS = ['/admin/migrations/run', '/admin/caches/clear', '/admin/deploy/run', '/admin/import', '/admin/import/preview'];

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (self::GET_PANELS as $panel) {
            $this->get($panel)->assertRedirect('/login');
        }

        foreach (self::WRITE_PANELS as $panel) {
            $this->post($panel)->assertRedirect('/login');
        }
    }

    public function test_non_admins_are_redirected_away_before_anything_runs(): void
    {
        $user = User::factory()->create(['admin_level' => 0]);

        foreach (self::GET_PANELS as $panel) {
            $this->actingAs($user)->get($panel)->assertRedirect('/');
        }

        foreach (self::WRITE_PANELS as $panel) {
            $this->actingAs($user)->post($panel)->assertRedirect('/');
        }
    }

    public function test_admins_can_open_the_panels(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        foreach (self::GET_PANELS as $panel) {
            $this->actingAs($admin)->get($panel)->assertOk();
        }
    }
}
