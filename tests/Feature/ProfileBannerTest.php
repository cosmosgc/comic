<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileBannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_banner_upload_stores_file_and_path(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'banner_image' => UploadedFile::fake()->image('banner.jpg', 1200, 400)->size(500),
        ])->assertRedirect(route('profile.show'));

        $path = $user->fresh()->banner_image_path;
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('storage/banners/', $path);
    }

    public function test_banner_can_be_removed(): void
    {
        $user = User::factory()->create(['banner_image_path' => 'storage/banners/old.jpg']);

        $this->actingAs($user)->post('/profile/update', [
            'name' => $user->name,
            'email' => $user->email,
            'remove_banner' => '1',
        ])->assertRedirect(route('profile.show'));

        $this->assertNull($user->fresh()->banner_image_path);
    }

    public function test_profile_pages_show_banner_when_set(): void
    {
        $user = User::factory()->create(['banner_image_path' => 'storage/banners/x.jpg']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('storage/banners/x.jpg');
        $this->get(route('profile.public.show.username', ['username' => $user->name]))
            ->assertOk()
            ->assertSee('storage/banners/x.jpg');
    }

    public function test_hovercard_endpoint_returns_profile_data(): void
    {
        $user = User::factory()->create([
            'bio' => 'Hello world',
            'banner_image_path' => 'storage/banners/x.jpg',
        ]);

        $this->getJson('/api/users/'.rawurlencode($user->name).'/card')
            ->assertOk()
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('bio', 'Hello world')
            ->assertJsonStructure(['avatar_url', 'banner_url', 'profile_url', 'comics_count', 'posts_count']);
    }

    public function test_hovercard_endpoint_404s_unknown_user(): void
    {
        $this->getJson('/api/users/DefinitelyNobody/card')->assertNotFound();
    }

    public function test_mention_links_carry_hovercard_attribute(): void
    {
        User::factory()->create(['name' => 'Hoverable']);

        $html = \App\Support\PostText::render('Hi @Hoverable');

        $this->assertStringContainsString('data-hovercard="Hoverable"', $html);
    }
}
