<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\MarketplaceListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SocialMetaTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['forum.enabled' => true]);
        config(['marketplace.enabled' => true]);
        config(['marketplace.require_approval' => false]);

        $this->artisan('migrate');
    }

    public function test_comic_page_renders_social_preview_with_cover_image(): void
    {
        $comic = Comic::factory()->create([
            'title' => 'Preview Comic',
            'description' => 'A comic description for sharing.',
            'image_path' => 'comics/preview.jpg',
        ]);

        $this->get(route('comics.showBySlug', ['slug' => $comic->slug]))
            ->assertSee('<meta property="og:title" content="Preview Comic">', false)
            ->assertSee('<meta property="og:description" content="A comic description for sharing.">', false)
            ->assertSee('<meta property="og:image" content="'.asset('storage/comics/preview.jpg').'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('storage/comics/preview.jpg').'">', false);
    }

    public function test_marketplace_listing_renders_social_preview_with_listing_image(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'title' => 'Preview Listing',
            'description' => 'A listing description for sharing.',
        ]);
        $listing->images()->create([
            'path' => 'marketplace/preview.jpg',
            'position' => 1,
        ]);

        $this->get(route('marketplace.show', ['listing' => $listing->slug]))
            ->assertSee('<meta property="og:title" content="Preview Listing — Mercado">', false)
            ->assertSee('<meta property="og:description" content="A listing description for sharing.">', false)
            ->assertSee('<meta property="og:image" content="'.asset('storage/marketplace/preview.jpg').'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('storage/marketplace/preview.jpg').'">', false);
    }

    public function test_forum_thread_renders_social_preview_with_banner_image(): void
    {
        $author = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();
        $thread = ForumThread::factory()->create([
            'forum_category_id' => $board->id,
            'user_id' => $author->id,
            'title' => 'Preview Thread',
            'banner_image_path' => 'storage/forum/preview.jpg',
        ]);
        ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => 'An opening post for sharing.',
            'position' => 1,
        ]);

        $this->get(route('forum.thread', [
            'categorySlug' => $board->slug,
            'threadSlug' => $thread->slug,
        ]))
            ->assertSee('<meta property="og:title" content="Preview Thread — Fórum">', false)
            ->assertSee('<meta property="og:description" content="An opening post for sharing.">', false)
            ->assertSee('<meta property="og:image" content="'.asset('storage/forum/preview.jpg').'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('storage/forum/preview.jpg').'">', false);
    }

    public function test_forum_thread_uses_an_opening_post_image_when_it_has_no_banner(): void
    {
        $author = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();
        $thread = ForumThread::factory()->create([
            'forum_category_id' => $board->id,
            'user_id' => $author->id,
            'banner_image_path' => null,
        ]);
        $openingPost = ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'user_id' => $author->id,
            'position' => 1,
        ]);
        $openingPost->attachments()->create([
            'user_id' => $author->id,
            'path' => 'forum/opening-image.jpg',
            'original_name' => 'opening-image.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
        ]);

        $this->get(route('forum.thread', [
            'categorySlug' => $board->slug,
            'threadSlug' => $thread->slug,
        ]))
            ->assertSee('<meta property="og:image" content="'.asset('storage/forum/opening-image.jpg').'">', false);
    }

    public function test_post_page_renders_social_preview_with_post_media(): void
    {
        $author = User::factory()->create(['name' => 'Preview Author']);
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'text' => 'A post description for sharing.',
            'media' => ['storage/posts/preview.jpg'],
        ]);

        $this->get(route('posts.show', ['post' => $post->id]))
            ->assertSee('<meta property="og:title" content="Post de Preview Author">', false)
            ->assertSee('<meta property="og:description" content="A post description for sharing.">', false)
            ->assertSee('<meta property="og:image" content="'.asset('storage/posts/preview.jpg').'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('storage/posts/preview.jpg').'">', false);
    }

    public function test_post_page_uses_the_site_image_when_the_post_has_no_media(): void
    {
        $post = Post::factory()->create([
            'text' => 'A text-only post for sharing.',
            'media' => null,
        ]);

        $this->get(route('posts.show', ['post' => $post->id]))
            ->assertSee('<meta property="og:image" content="'.Vite::asset('resources/images/icon.png').'">', false);
    }

    public function test_post_page_reads_legacy_json_media_for_social_preview(): void
    {
        $post = Post::factory()->create([
            'media' => json_encode(['storage/posts/legacy-preview.jpg']),
        ]);

        $this->get(route('posts.show', ['post' => $post->id]))
            ->assertSee('<meta property="og:image" content="'.asset('storage/posts/legacy-preview.jpg').'">', false)
            ->assertSee('<meta name="twitter:image" content="'.asset('storage/posts/legacy-preview.jpg').'">', false);
    }
}
