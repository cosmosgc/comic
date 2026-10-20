<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
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

    public function test_admin_can_filter_comics_without_tags(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $untagged = Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Comic sem tags',
        ]);
        $tagged = Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Comic com tags',
        ]);
        $tagged->tags()->attach(Tag::create(['name' => 'tagged'])->id);

        $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'untagged']))
            ->assertOk()
            ->assertSee('Comic sem tags')
            ->assertDontSee('Comic com tags');
    }

    public function test_admin_can_filter_potential_duplicate_comics(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'O Último Desejo do Lobo',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => '[Zourik] O Ultimo DESEJO do Lobo (Zootopia) [Português-BR]',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Uma história completamente diferente',
        ]);

        $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'duplicates']))
            ->assertOk()
            ->assertSee('O Último Desejo do Lobo')
            ->assertSee('[Zourik] O Ultimo DESEJO do Lobo')
            ->assertDontSee('Uma história completamente diferente');
    }

    public function test_admin_cannot_use_an_unknown_comic_attention_filter(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'reported']))
            ->assertSessionHasErrors('attention');
    }
}
