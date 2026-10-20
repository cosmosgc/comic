<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ForumCategoryBannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_admin_can_upload_a_banner_when_creating_a_category(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $section = ForumCategory::factory()->create();

        $this->actingAs($admin)->post('/admin/forum/categories', [
            'name' => 'Quadro com banner',
            'parent_id' => $section->id,
            'min_role' => 'member',
            'is_visible' => '1',
            'banner' => UploadedFile::fake()->image('hero.jpg', 1600, 500),
            'banner_position_x' => 25,
            'banner_position_y' => 75,
        ])->assertRedirect(route('admin.forum.categories'));

        $category = ForumCategory::where('name', 'Quadro com banner')->firstOrFail();
        $path = $category->banner_image_path;
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('storage/forum/categories/', $path);
        $this->assertFileExists(public_path($path));
        $this->assertSame(25, $category->banner_position_x);
        $this->assertSame(75, $category->banner_position_y);

        @unlink(public_path($path));
    }

    public function test_admin_can_replace_and_remove_a_banner(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $oldRelative = 'storage/forum/categories/old-hero.jpg';
        File::ensureDirectoryExists(dirname(public_path($oldRelative)));
        file_put_contents(public_path($oldRelative), 'old-bytes');

        $category = ForumCategory::factory()->board()->create([
            'banner_image_path' => $oldRelative,
        ]);

        $this->actingAs($admin)->put('/admin/forum/categories/'.$category->id, [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'min_role' => 'member',
            'is_visible' => '1',
            'banner' => UploadedFile::fake()->image('novo.jpg', 1400, 400),
        ])->assertRedirect(route('admin.forum.categories'));

        $replaced = $category->fresh()->banner_image_path;
        $this->assertNotSame($oldRelative, $replaced);
        $this->assertFileDoesNotExist(public_path($oldRelative));
        $this->assertFileExists(public_path($replaced));

        $this->actingAs($admin)->put('/admin/forum/categories/'.$category->id, [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'min_role' => 'member',
            'is_visible' => '1',
            'remove_banner' => '1',
        ])->assertRedirect(route('admin.forum.categories'));

        $this->assertNull($category->fresh()->banner_image_path);
        $this->assertFileDoesNotExist(public_path($replaced));
    }

    public function test_banner_must_be_an_image(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $section = ForumCategory::factory()->create();

        $this->actingAs($admin)->post('/admin/forum/categories', [
            'name' => 'Arquivo inválido',
            'parent_id' => $section->id,
            'min_role' => 'member',
            'is_visible' => '1',
            'banner' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
        ])->assertSessionHasErrors('banner');

        $this->assertNull(ForumCategory::where('name', 'Arquivo inválido')->first()?->banner_image_path);
    }

    public function test_category_page_renders_the_hero_banner(): void
    {
        $board = ForumCategory::factory()->board()->create([
            'name' => 'Banner grande',
            'banner_image_path' => 'storage/forum/categories/hero.jpg',
            'banner_position_x' => 10,
            'banner_position_y' => 90,
        ]);

        $this->get('/forum/'.$board->slug)
            ->assertOk()
            ->assertSee('storage/forum/categories/hero.jpg')
            ->assertSee('object-position: 10% 90%', false)
            ->assertSee('Banner de Banner grande')
            ->assertSee('min-h-44');
    }

    public function test_category_form_previews_the_current_banner(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $board = ForumCategory::factory()->board()->create([
            'banner_image_path' => 'storage/forum/categories/hero.jpg',
        ]);

        $this->actingAs($admin)
            ->get('/admin/forum/categories/'.$board->id.'/edit')
            ->assertOk()
            ->assertSee('category-banner-preview')
            ->assertSee('storage/forum/categories/hero.jpg')
            ->assertSee('name="banner_position_x"', false);
    }

    public function test_forum_index_shows_the_board_banner_thumbnail(): void
    {
        ForumCategory::factory()->board()->create([
            'banner_image_path' => 'storage/forum/categories/thumb.jpg',
        ]);

        $this->get('/forum')
            ->assertOk()
            ->assertSee('storage/forum/categories/thumb.jpg');
    }
}
