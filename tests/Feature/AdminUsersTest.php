<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Admin user list (issues.md #10): the table shows a role badge derived
 * from admin_level — it used to print a `role` attribute that does not
 * exist — and the list only selects the columns it renders.
 */
class AdminUsersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_user_list_shows_a_role_badge_for_each_account(): void
    {
        $admin = User::factory()->create(['admin_level' => 1, 'name' => 'Adminzinho']);
        User::factory()->create(['admin_level' => 0, 'name' => 'Membrozinho']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Adminzinho')
            ->assertSee('Membrozinho')
            ->assertSee('admin-badge-success">Admin', false)
            ->assertSee('>Usuário</span>', false);
    }

    public function test_non_admin_cannot_open_the_user_list(): void
    {
        $member = User::factory()->create(['admin_level' => 0]);

        $this->actingAs($member)->get('/admin/users')->assertRedirect('/');
    }
}
