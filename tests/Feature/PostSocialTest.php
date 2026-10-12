<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Post;
use App\Models\User;
use App\Support\LinkPreview;
use App\Support\PostText;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostSocialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_search_filters_by_text_and_author(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        Post::factory()->create(['author_id' => $alice->id, 'text' => 'Hello galaxies']);
        Post::factory()->create(['text' => 'Unrelated post']);

        $this->get('/posts?q=galaxies')
            ->assertOk()
            ->assertSee('Hello galaxies')
            ->assertDontSee('Unrelated post');

        $this->get('/posts?q=Alice')->assertOk()->assertSee('Hello galaxies');
    }

    public function test_trending_orders_by_engagement(): void
    {
        $quiet = Post::factory()->create(['text' => 'quiet post', 'created_at' => now()->subDay()]);
        $hot = Post::factory()->create(['text' => 'hot post', 'created_at' => now()->subDay()]);
        $liker = User::factory()->create();
        $hot->likedByUsers()->attach($liker->id);

        $response = $this->get('/posts?tab=trending')->assertOk();
        $this->assertTrue(strpos($response->getContent(), 'hot post') < strpos($response->getContent(), 'quiet post'));
    }

    public function test_rich_text_linkifies_urls_mentions_and_escapes_html(): void
    {
        $bob = User::factory()->create(['name' => 'Bob']);

        $html = PostText::render('Hi @Bob see https://example.com/x <script>alert(1)</script>');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('href="https://example.com/x"', $html);
        $this->assertStringContainsString(
            'href="'.route('profile.public.show.username', ['username' => $bob->name]).'"',
            $html
        );
        $this->assertStringContainsString('@Bob', $html);
    }

    public function test_unknown_mentions_stay_plain_text(): void
    {
        $html = PostText::render('Hi @NobodyHere');

        $this->assertStringContainsString('@NobodyHere', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_owner_can_edit_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id, 'text' => 'Before']);

        $this->actingAs($author)->get("/posts/{$post->id}/edit")->assertOk();
        $this->actingAs($author)
            ->put("/posts/{$post->id}", ['text' => 'After'])
            ->assertRedirect(route('posts.show', $post));

        $this->assertSame('After', $post->fresh()->text);
    }

    public function test_intruder_and_guest_cannot_edit_post(): void
    {
        $author = User::factory()->create();
        $intruder = User::factory()->create(['admin_level' => 0]);
        $post = Post::factory()->create(['author_id' => $author->id, 'text' => 'Before']);

        $this->actingAs($intruder)->get("/posts/{$post->id}/edit")->assertForbidden();
        $this->actingAs($intruder)->put("/posts/{$post->id}", ['text' => 'Hacked'])->assertForbidden();

        $this->assertSame('Before', $post->fresh()->text);
    }

    public function test_guest_is_redirected_from_post_edit(): void
    {
        $post = Post::factory()->create();

        $this->get("/posts/{$post->id}/edit")->assertRedirect('/login');
    }

    public function test_youtube_preview_uses_oembed_title(): void
    {
        Http::fake([
            'youtube.com/oembed*' => Http::response(['title' => 'Cool video'], 200),
        ]);

        $preview = LinkPreview::forUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame('youtube', $preview['type']);
        $this->assertSame('Cool video', $preview['title']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $preview['thumbnail']);
    }

    public function test_internal_comic_link_previews_comic(): void
    {
        $comic = Comic::factory()->create();

        $preview = LinkPreview::forUrl(url("/comics/{$comic->slug}"));

        $this->assertSame('comic', $preview['type']);
        $this->assertSame($comic->title, $preview['title']);
    }

    public function test_generic_link_previews_host_card(): void
    {
        $preview = LinkPreview::forUrl('https://example.com/some/page');

        $this->assertSame('link', $preview['type']);
        $this->assertSame('example.com', $preview['host']);
    }
}
