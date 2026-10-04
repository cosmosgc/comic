<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_index_lists_entries(): void
    {
        $response = $this->get('/changelog');

        $response->assertStatus(200);
        $response->assertSee("What's new", false);
        $response->assertSee('Admin Migrations panel');
    }

    public function test_index_search_filters_entries(): void
    {
        $response = $this->get('/changelog?q=413');

        $response->assertStatus(200);
        $response->assertSee('Sequential comic uploads');
        $response->assertDontSee('Admin Migrations panel');
    }

    public function test_show_renders_single_entry(): void
    {
        $response = $this->get('/changelog/2026-10-04-admin-migrations-panel');

        $response->assertStatus(200);
        $response->assertSee('Admin Migrations panel');
        $response->assertSee('Related updates');
    }

    public function test_show_unknown_entry_404s(): void
    {
        $response = $this->get('/changelog/no-such-entry');

        $response->assertStatus(404);
    }
}
