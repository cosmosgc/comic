<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use Tests\TestCase;

class AdminComicListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_list_shows_cover_thumbnail_and_placeholder(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Com capa',
            'image_path' => 'comics/1/cover.jpg',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Sem capa',
            'image_path' => null,
        ]);

        $this->actingAs($admin)->get('/admin/comics')
            ->assertOk()
            ->assertSee('storage/comics/1/cover.jpg')
            ->assertSee('Capa de Com capa')
            ->assertSee('data-cover-placeholder', false);
    }
}
