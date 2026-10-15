<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Services\TagCategorizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TagCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        $this->artisan('migrate');
    }

    protected function admin(): User
    {
        return User::factory()->create(['admin_level' => 1]);
    }

    public function test_migration_seeds_default_categories_with_flask_colors(): void
    {
        $names = TagCategory::orderBy('sort_order')->pluck('name')->all();

        $this->assertSame(['artist', 'copyright', 'character', 'species', 'general', 'meta', 'lore'], $names);
        $this->assertSame('#372414', TagCategory::where('name', 'artist')->first()->background);
        $this->assertSame('#ffe3c2', TagCategory::where('name', 'artist')->first()->text_color);
        $this->assertSame('#655fd2', TagCategory::where('name', 'character')->first()->border);
    }

    public function test_categorizer_prefers_namespace_mapping(): void
    {
        $categorizer = app(TagCategorizer::class);

        $this->assertSame('artist', $categorizer->forName('artist:gentsuki')->name);
        $this->assertSame('copyright', $categorizer->forName('parody:original')->name);
        $this->assertSame('character', $categorizer->forName('character:echo')->name);
        $this->assertSame('species', $categorizer->forName('species:eeveelution')->name);
        $this->assertSame('general', $categorizer->forName('female:stockings')->name);
        $this->assertSame('meta', $categorizer->forName('meta:hi_res_post')->name);
    }

    public function test_categorizer_exact_keyword_beats_substring(): void
    {
        $categorizer = app(TagCategorizer::class);

        // "pokemon" is a copyright keyword, but the exact species entry wins.
        $this->assertSame('species', $categorizer->forName('pokemon_(species)')->name);
        $this->assertSame('copyright', $categorizer->forName('pokemon')->name);
        $this->assertSame('meta', $categorizer->forName('Hi_Res')->name);
        $this->assertSame('general', $categorizer->forName('some random tag')->name);
    }

    public function test_find_or_create_categorized_assigns_and_backfills(): void
    {
        $tag = Tag::findOrCreateCategorized('artist:someone');

        $this->assertSame('artist', $tag->fresh()->category->name);

        // Legacy tag without a category gets backfilled on touch.
        $legacy = Tag::create(['name' => 'leafeon']);
        $this->assertNull($legacy->category_id);

        $found = Tag::findOrCreateCategorized('leafeon');

        $this->assertSame($legacy->id, $found->id);
        $this->assertSame('species', $found->fresh()->category->name);
    }

    public function test_comic_update_categorizes_new_tags(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/comics/{$comic->id}/update", [
                'title' => $comic->title,
                'tags' => 'artist:gentsuki, pokemon',
            ])
            ->assertRedirect(route('comics.edit', $comic->id));

        $tags = $comic->fresh()->tags->keyBy('name');

        $this->assertSame('artist', $tags['artist:gentsuki']->category->name);
        $this->assertSame('copyright', $tags['pokemon']->category->name);
    }

    public function test_guests_and_non_admins_cannot_manage_categories(): void
    {
        $this->get('/admin/tag-categories')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->get('/admin/tag-categories')
            ->assertRedirect('/');
    }

    public function test_admin_can_crud_categories(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/tag-categories', [
                'name' => 'vehicles',
                'label' => 'Vehicles',
                'background' => '#112233',
                'border' => '#445566',
                'text_color' => '#aabbcc',
                'namespaces' => 'vehicle, car',
                'keywords' => 'tank, spaceship',
                'sort_order' => 8,
            ])
            ->assertRedirect(route('admin.tag-categories'));

        $category = TagCategory::where('name', 'vehicles')->firstOrFail();
        $this->assertSame(['vehicle', 'car'], $category->namespaces);
        $this->assertSame(['tank', 'spaceship'], $category->keywords);

        // Keywords take effect immediately for new tags.
        $this->assertSame('vehicles', app(TagCategorizer::class)->forName('spaceship')->name);

        $this->actingAs($this->admin())
            ->put("/admin/tag-categories/{$category->id}", [
                'name' => 'vehicles',
                'label' => 'Machines',
                'background' => '#112233',
                'border' => '#445566',
                'text_color' => '#aabbcc',
                'namespaces' => '',
                'keywords' => '',
                'sort_order' => 8,
            ])
            ->assertRedirect(route('admin.tag-categories'));

        $this->assertSame('Machines', $category->fresh()->label);

        Tag::findOrCreateCategorized('tank');

        $this->actingAs($this->admin())
            ->delete("/admin/tag-categories/{$category->id}")
            ->assertRedirect(route('admin.tag-categories'));

        $this->assertNull(TagCategory::find($category->id));
        // Orphaned tags fall back to general.
        $this->assertSame('general', Tag::where('name', 'tank')->first()->fresh()->category->name);
    }

    public function test_admin_category_rejects_bad_colors(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/tag-categories', [
                'name' => 'bad',
                'label' => 'Bad',
                'background' => 'not-a-color',
                'border' => '#445566',
                'text_color' => '#aabbcc',
            ])
            ->assertSessionHasErrors('background');

        $this->assertNull(TagCategory::where('name', 'bad')->first());
    }

    public function test_admin_recategorize_updates_all_tags(): void
    {
        Tag::create(['name' => 'mystery-tag']);
        Tag::findOrCreateCategorized('hi_res');

        // Simulate edited keywords: move hi_res out of meta.
        $meta = TagCategory::where('name', 'meta')->firstOrFail();
        $meta->update(['keywords' => []]);

        $this->actingAs($this->admin())
            ->post('/admin/tag-categories/recategorize')
            ->assertRedirect(route('admin.tag-categories'))
            ->assertSessionHas('success');

        $this->assertSame('general', Tag::where('name', 'mystery-tag')->first()->fresh()->category->name);
        $this->assertSame('general', Tag::where('name', 'hi_res')->first()->fresh()->category->name);
    }

    public function test_autocomplete_returns_category_styles(): void
    {
        Tag::findOrCreateCategorized('leafeon');

        $this->getJson('/api/tags?q=leaf')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'leafeon',
                'category' => [
                    'name' => 'species',
                    'label' => 'Species',
                    'background' => '#203322',
                    'border' => '#4d8c4f',
                    'text' => '#dcffdf',
                ],
            ]);
    }

    public function test_lookup_returns_category_for_new_and_known_tags(): void
    {
        Tag::findOrCreateCategorized('stockings');

        Http::fake([
            'api.e-hentai.org/*' => Http::response([
                'gmetadata' => [[
                    'gid' => 1,
                    'token' => 'aaaaaaaaaa',
                    'title' => 'Gallery',
                    'tags' => ['female:stockings', 'artist:nobody'],
                ]],
            ], 200),
        ]);

        $response = $this->getJson('/api/tags/lookup?q='.urlencode('https://e-hentai.org/g/1/aaaaaaaaaa/'))
            ->assertOk();

        // Known tag carries its stored category; new ones are previewed.
        $response->assertJsonFragment(['full' => 'female:stockings', 'matched' => true]);
        $tags = collect($response->json('galleries.0.tags'))->keyBy('full');

        $this->assertSame('general', $tags['female:stockings']['category']['name']);
        $this->assertSame('artist', $tags['artist:nobody']['category']['name']);
    }
}
