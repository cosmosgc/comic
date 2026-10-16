<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Comic;
use App\Models\Comment;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminComicDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_admin_deletes_comic_with_files_and_relations(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id]);
        $comic->pages()->create(['image_path' => "comics/{$comic->id}/page.jpg", 'page_number' => 1]);
        $comic->tags()->attach(Tag::create(['name' => 'doomed'])->id);
        $comic->likedByUsers()->attach($admin->id);
        $comment = new Comment(['body' => 'bye']);
        $comment->user()->associate($admin);
        $comment->comic()->associate($comic);
        $comment->save();
        $comic->collections()->attach(Collection::create(['name' => 'C', 'user_id' => $admin->id])->id);

        $dir = public_path("storage/comics/{$comic->id}");
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/page.jpg', 'fake');

        $this->actingAs($admin)->delete("/admin/comics/{$comic->id}")
            ->assertRedirect(route('admin.comics'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('comics', ['id' => $comic->id]);
        $this->assertDatabaseMissing('pages', ['comic_id' => $comic->id]);
        $this->assertSame(0, DB::table('comic_tag')->where('comic_id', $comic->id)->count());
        $this->assertSame(0, DB::table('comic_user_likes')->where('comic_id', $comic->id)->count());
        $this->assertSame(0, Comment::where('comic_id', $comic->id)->count());
        $this->assertSame(0, DB::table('collection_comic')->where('comic_id', $comic->id)->count());
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_guest_and_non_admin_cannot_delete_comic(): void
    {
        $comic = Comic::factory()->create();

        $this->delete("/admin/comics/{$comic->id}")->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->delete("/admin/comics/{$comic->id}")->assertRedirect('/');

        $this->assertDatabaseHas('comics', ['id' => $comic->id]);
        File::deleteDirectory(public_path("storage/comics/{$comic->id}"));
    }
}
