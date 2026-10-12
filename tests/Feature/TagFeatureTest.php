<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\User;
use Tests\TestCase;

class TagFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_owner_can_replace_tags_on_update(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);
        $comic->tags()->attach(Tag::create(['name' => 'Old'])->id);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", [
                'title' => $comic->title,
                'tags' => 'Action, Fantasy, ,Action',
            ])
            ->assertRedirect(route('comics.edit', $comic->id));

        $this->assertEqualsCanonicalizing(
            ['Action', 'Fantasy'],
            $comic->fresh()->tags->pluck('name')->all()
        );
    }

    public function test_update_without_tags_key_keeps_existing_tags(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);
        $comic->tags()->attach(Tag::create(['name' => 'Kept'])->id);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", ['title' => 'New title'])
            ->assertRedirect(route('comics.edit', $comic->id));

        $this->assertEquals(['Kept'], $comic->fresh()->tags->pluck('name')->all());
    }

    public function test_update_saves_description(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", [
                'title' => $comic->title,
                'description' => 'A brand new synopsis.',
            ])
            ->assertRedirect(route('comics.edit', $comic->id));

        $this->assertSame('A brand new synopsis.', $comic->fresh()->description);
    }

    public function test_non_owner_cannot_change_tags(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['admin_level' => 0]);
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->put("/comics/{$comic->id}/update", ['title' => $comic->title, 'tags' => 'Hacked'])
            ->assertForbidden();

        $this->assertCount(0, $comic->fresh()->tags);
    }

    public function test_tag_autocomplete_returns_matches(): void
    {
        Tag::create(['name' => 'Action']);
        Tag::create(['name' => 'Live Action']);
        Tag::create(['name' => 'Fantasy']);

        $this->getJson('/api/tags?q=action')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Action'])
            ->assertJsonMissing(['name' => 'Fantasy']);
    }

    public function test_tag_autocomplete_lists_popular_first(): void
    {
        $popular = Tag::create(['name' => 'Popular']);
        $rare = Tag::create(['name' => 'Popularish']);
        Comic::factory()->create()->tags()->attach($popular->id);

        $names = $this->getJson('/api/tags')->assertOk()->json('*.name');

        $this->assertSame('Popular', $names[0]);
        $this->assertContains('Popularish', $names);
    }
}
