<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Support\PostText;
use Tests\TestCase;

class HashtagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_posting_with_a_hashtag_attaches_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['text' => 'Loving #Art and #art'])->assertRedirect();

        $this->assertDatabaseHas('hashtags', ['tag' => 'art']);
        $this->assertSame(1, Hashtag::where('tag', 'art')->first()->posts()->count());
    }

    public function test_hashtag_page_lists_matching_posts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['text' => 'A post about #space comics'])->assertRedirect();

        $this->get('/hashtag/space')
            ->assertOk()
            ->assertSee('A post about')
            ->assertSee('#space');
    }

    public function test_unknown_hashtag_page_404s(): void
    {
        $this->get('/hashtag/nothinghere')->assertNotFound();
    }

    public function test_trending_lists_used_tags(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['text' => 'hello #trendme'])->assertRedirect();

        $trending = Hashtag::trending();

        $this->assertTrue($trending->contains(fn ($tag) => $tag->tag === 'trendme'));
    }

    public function test_post_text_renders_hashtag_links(): void
    {
        $html = PostText::render('check #cool tag');

        $this->assertStringContainsString('href="'.route('hashtags.show', ['tag' => 'cool']).'"', $html);
        $this->assertStringContainsString('#cool', $html);
    }

    public function test_updating_a_post_resyncs_hashtags(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $user->id, 'text' => 'old #first']);
        Hashtag::syncForPost($post, $post->text);

        $this->actingAs($user)->put("/posts/{$post->id}", ['text' => 'new #second'])->assertRedirect();

        $this->assertDatabaseHas('hashtags', ['tag' => 'second']);
        $this->assertSame(0, Hashtag::where('tag', 'first')->first()->posts()->count());
    }
}
