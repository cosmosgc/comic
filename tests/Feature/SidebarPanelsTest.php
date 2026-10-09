<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\User;
use App\View\Composers\SidebarComposer;
use Tests\TestCase;

class SidebarPanelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_popular_tags_are_limited(): void
    {
        foreach (range(1, 15) as $i) {
            $tag = Tag::create(['name' => "tag-{$i}-".uniqid()]);
            $comic = Comic::factory()->create();
            $comic->tags()->attach($tag->id);
        }

        $tags = SidebarComposer::popularTags();

        $this->assertLessThanOrEqual(SidebarComposer::POPULAR_TAGS_LIMIT, $tags->count());
    }

    public function test_popular_tags_prefer_most_liked(): void
    {
        $voters = User::factory()->count(3)->create();

        $likedTag = Tag::create(['name' => 'liked-'.uniqid()]);
        $likedComic = Comic::factory()->create();
        $likedComic->tags()->attach($likedTag->id);
        $likedComic->likedByUsers()->attach($voters->pluck('id'));

        $plainTag = Tag::create(['name' => 'plain-'.uniqid()]);
        $plainComic = Comic::factory()->create();
        $plainComic->tags()->attach($plainTag->id);

        // Run several times: the randomized threshold must never
        // exclude the top-liked tag from the candidate pool query,
        // and with only 2 tags the liked one always fits the limit.
        $seen = collect();
        foreach (range(1, 5) as $_) {
            $seen = $seen->merge(SidebarComposer::popularTags()->pluck('id'));
        }

        $this->assertContains($likedTag->id, $seen);
    }

    public function test_latest_uploads_returns_newest_first(): void
    {
        $old = Comic::factory()->create(['created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);
        $new = Comic::factory()->create();

        $latest = SidebarComposer::latestUploads(5);

        $this->assertSame($new->id, $latest->first()->id);
        $this->assertContains($old->id, $latest->pluck('id'));
    }

    public function test_recommended_prefers_liked_and_falls_back(): void
    {
        // Empty site falls back to an empty collection, not an error.
        $this->assertTrue(SidebarComposer::recommended()->isEmpty());

        $voters = User::factory()->count(2)->create();
        $top = Comic::factory()->create(['title' => 'Top Rec '.uniqid()]);
        $top->likedByUsers()->attach($voters->pluck('id'));
        Comic::factory()->create(['title' => 'Unliked Rec '.uniqid()]);

        $seen = collect();
        foreach (range(1, 5) as $_) {
            $seen = $seen->merge(SidebarComposer::recommended(5)->pluck('id'));
        }

        $this->assertContains($top->id, $seen);
    }

    public function test_index_renders_all_three_panels(): void
    {
        $comic = Comic::factory()->create(['title' => 'Panel Comic '.uniqid()]);
        $tag = Tag::create(['name' => 'panel-tag-'.uniqid()]);
        $comic->tags()->attach($tag->id);

        $this->get('/comics')
            ->assertOk()
            ->assertSee('Tags Populares')
            ->assertSee('Latest Uploads')
            ->assertSee('Recommended')
            ->assertSee('Panel Comic')
            ->assertDontSee('Coming soon');
    }
}
