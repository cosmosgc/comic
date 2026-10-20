<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ForumThreadBannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_thread_owner_can_upload_a_banner(): void
    {
        $owner = User::factory()->create();
        $thread = ForumThread::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->put('/forum/threads/'.$thread->id, [
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
        ])->assertRedirect();

        $path = $thread->fresh()->banner_image_path;
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('storage/forum/banners/', $path);
        $this->assertFileExists(public_path($path));

        @unlink(public_path($path));
    }

    public function test_banner_can_be_set_while_creating_a_thread(): void
    {
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, [
            'title' => 'Tópico com banner',
            'body' => 'Olá!',
            'banner' => UploadedFile::fake()->image('capa.jpg', 1200, 400),
            'banner_position_x' => 15,
            'banner_position_y' => 85,
        ])->assertRedirect();

        $thread = ForumThread::firstOrFail();
        $path = $thread->banner_image_path;
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('storage/forum/banners/', $path);
        $this->assertFileExists(public_path($path));
        $this->assertSame(15, $thread->banner_position_x);
        $this->assertSame(85, $thread->banner_position_y);

        @unlink(public_path($path));
    }

    public function test_thread_owner_can_change_the_banner_focal_point(): void
    {
        $owner = User::factory()->create();
        $thread = ForumThread::factory()->create([
            'user_id' => $owner->id,
            'banner_image_path' => 'storage/forum/banners/x.jpg',
        ]);

        $this->actingAs($owner)->put('/forum/threads/'.$thread->id, [
            'banner_position_x' => 20,
            'banner_position_y' => 80,
        ])->assertRedirect();

        $thread->refresh();
        $this->assertSame(20, $thread->banner_position_x);
        $this->assertSame(80, $thread->banner_position_y);
        $this->assertSame('20% 80%', $thread->bannerPosition());
    }

    public function test_stranger_cannot_change_the_banner(): void
    {
        $thread = ForumThread::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->put('/forum/threads/'.$thread->id, [
            'banner' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertForbidden();

        $this->assertNull($thread->fresh()->banner_image_path);
    }

    public function test_guests_cannot_change_the_banner(): void
    {
        $thread = ForumThread::factory()->create();

        $this->put('/forum/threads/'.$thread->id, [
            'banner' => UploadedFile::fake()->image('banner.jpg'),
        ])->assertRedirect(route('login'));

        $this->assertNull($thread->fresh()->banner_image_path);
    }

    public function test_banner_can_be_removed_and_the_file_is_deleted(): void
    {
        $owner = User::factory()->create();
        $relative = 'storage/forum/banners/old-banner.jpg';
        File::ensureDirectoryExists(dirname(public_path($relative)));
        file_put_contents(public_path($relative), 'old-bytes');

        $thread = ForumThread::factory()->create([
            'user_id' => $owner->id,
            'banner_image_path' => $relative,
        ]);

        $this->actingAs($owner)->put('/forum/threads/'.$thread->id, [
            'remove_banner' => '1',
        ])->assertRedirect();

        $this->assertNull($thread->fresh()->banner_image_path);
        $this->assertFileDoesNotExist(public_path($relative));
    }

    public function test_banner_must_be_an_image(): void
    {
        $owner = User::factory()->create();
        $thread = ForumThread::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->put('/forum/threads/'.$thread->id, [
            'banner' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
        ])->assertSessionHasErrors('banner');

        $this->assertNull($thread->fresh()->banner_image_path);
    }

    public function test_new_thread_form_has_a_banner_preview(): void
    {
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs(User::factory()->create())
            ->get('/forum/'.$board->slug)
            ->assertOk()
            ->assertSee('thread-banner-preview')
            ->assertSee('name="banner_position_x"', false)
            ->assertSee('name="banner_position_y"', false);
    }

    public function test_thread_page_renders_the_banner_and_owner_controls(): void
    {
        $owner = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();
        $thread = ForumThread::factory()->create([
            'forum_category_id' => $board->id,
            'user_id' => $owner->id,
            'banner_image_path' => 'storage/forum/banners/x.jpg',
            'banner_position_x' => 30,
            'banner_position_y' => 70,
        ]);

        $this->actingAs($owner)
            ->get('/forum/'.$board->slug.'/'.$thread->slug)
            ->assertOk()
            ->assertSee('storage/forum/banners/x.jpg')
            ->assertSee('object-position: 30% 70%', false)
            ->assertSee('Banner do tópico')
            ->assertSee('Salvar banner')
            ->assertSee('thread-banner-preview')
            ->assertSee('name="banner_position_x"', false);

        $this->actingAs(User::factory()->create())
            ->get('/forum/'.$board->slug.'/'.$thread->slug)
            ->assertOk()
            ->assertSee('storage/forum/banners/x.jpg')
            ->assertDontSee('Salvar banner');
    }
}
