<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\TagCategory;
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

    public function test_upcoming_comics_are_ordered_and_limited(): void
    {
        $first = Comic::factory()->create(['published_at' => now()->addDay()]);
        $second = Comic::factory()->create(['published_at' => now()->addDays(2)]);
        Comic::factory()->create(['published_at' => now()->subDay()]);
        Comic::factory()->count(SidebarComposer::SIDEBAR_COMICS_LIMIT + 1)->create([
            'published_at' => now()->addDays(3),
        ]);

        $upcoming = SidebarComposer::upcoming();

        $this->assertCount(SidebarComposer::SIDEBAR_COMICS_LIMIT, $upcoming);
        $this->assertSame([$first->id, $second->id], $upcoming->take(2)->pluck('id')->all());
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

    public function test_popular_tags_hide_tags_from_hidden_categories(): void
    {
        $hiddenCategory = TagCategory::create([
            'name' => 'hidden-'.uniqid(),
            'label' => 'Hidden',
            'background' => '#123456',
            'border' => '#123456',
            'text_color' => '#ffffff',
            'is_visible' => false,
        ]);
        $visibleCategory = TagCategory::create([
            'name' => 'visible-'.uniqid(),
            'label' => 'Visible',
            'background' => '#654321',
            'border' => '#654321',
            'text_color' => '#ffffff',
            'is_visible' => true,
        ]);

        $hiddenTag = Tag::create(['name' => 'hidden-'.uniqid(), 'category_id' => $hiddenCategory->id]);
        $visibleTag = Tag::create(['name' => 'visible-'.uniqid(), 'category_id' => $visibleCategory->id]);
        $untagged = Tag::create(['name' => 'plain-'.uniqid()]);

        foreach ([$hiddenTag, $visibleTag, $untagged] as $tag) {
            Comic::factory()->create()->tags()->attach($tag->id);
        }

        $ids = SidebarComposer::popularTags(50)->pluck('id');

        $this->assertNotContains($hiddenTag->id, $ids);
        $this->assertContains($visibleTag->id, $ids);
        $this->assertContains($untagged->id, $ids);
    }

    public function test_index_renders_all_three_panels(): void
    {
        $comic = Comic::factory()->create(['title' => 'Panel Comic '.uniqid()]);
        $tag = Tag::create(['name' => 'panel-tag-'.uniqid()]);
        $comic->tags()->attach($tag->id);

        $this->get('/comics')
            ->assertOk()
            ->assertSee('Tags populares')
            ->assertSee('Enviados recentes')
            ->assertSee('Recomendados')
            ->assertSee('Panel Comic')
            ->assertDontSee('Coming soon');
    }

    public function test_index_obscures_far_ahead_comics_in_the_upcoming_sidebar(): void
    {
        $comic = Comic::factory()->create([
            'title' => 'Spoiler da lateral XYZ',
            'image_path' => 'comics/spoiler-da-lateral.jpg',
            'published_at' => now()->addDays(Comic::SCHEDULE_PREVIEW_DAYS + 1),
        ]);

        $this->get('/comics')
            ->assertSee('Próximos lançamentos')
            ->assertSee('data-upcoming-sidebar="'.$comic->id.'"', false)
            ->assertSee('Comic em breve')
            ->assertSee('blur-sm', false)
            ->assertDontSee('Spoiler da lateral XYZ');
    }
}
