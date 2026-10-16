<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Services\TagCategorizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
        $this->assertSame('meta', $categorizer->forName('meta:hi_res_post')->name);
        $this->assertSame('general', $categorizer->forName('language:portuguese')->name);
        $this->assertSame('general', $categorizer->forName('female:apron')->name);
    }

    public function test_categorizer_applies_eh_attribute_rules(): void
    {
        $categorizer = app(TagCategorizer::class);

        // Unknown namespaces resolve to their own auto-created category,
        // beating keywords: "male:dog boy" lands in Male, not Species.
        $male = $categorizer->resolveForSaving('male:dog boy');
        $this->assertSame('dog boy', $male['name']);
        $this->assertSame('male', $male['category']->name);
        $this->assertTrue($male['auto_create'] || $male['category']->wasRecentlyCreated);

        $female = $categorizer->resolveForSaving('female:furry');
        $this->assertSame('furry', $female['name']);
        $this->assertSame('female', $female['category']->name);

        // Later cases append to the existing namespace category.
        $again = $categorizer->resolveForSaving('male:sole male');
        $this->assertSame('sole male', $again['name']);
        $this->assertSame($male['category']->id, $again['category']->id);

        $this->assertSame('language', $categorizer->resolveForSaving('language:translated')['category']->name);
        $this->assertSame('other', $categorizer->resolveForSaving('other:full color')['category']->name);

        // Plain tags still use keywords — and learned vocabulary outranks
        // seeds: "furry" was just taught to Female above, so it stays
        // Female instead of flipping to Species.
        $this->assertSame('female', $categorizer->resolveForSaving('furry')['category']->name);
        $this->assertSame('species', $categorizer->resolveForSaving('eeveelution')['category']->name);
        // Same for "translated": Language learned it from
        // "language:translated" above, so it stays there.
        $this->assertSame('language', $categorizer->resolveForSaving('translated')['category']->name);
        $this->assertSame('meta', $categorizer->resolveForSaving('monochrome')['category']->name);
        $this->assertSame('general', $categorizer->resolveForSaving('apron')['category']->name);
    }

    public function test_auto_created_category_has_random_hex_style(): void
    {
        $category = app(TagCategorizer::class)->resolveForSaving('female:apron')['category'];

        $this->assertSame('female', $category->name);
        $this->assertSame(['female'], $category->namespaces);
        foreach (['background', 'border', 'text_color'] as $field) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $category->$field);
        }
    }

    public function test_namespaced_imports_teach_their_category(): void
    {
        $categorizer = app(TagCategorizer::class);

        $categorizer->resolveForSaving('male:dog boy');
        $this->assertSame(['dog boy'], TagCategory::where('name', 'male')->first()->learned_keywords);

        // Later cases append.
        $categorizer->resolveForSaving('male:furry');
        $this->assertEqualsCanonicalizing(
            ['dog boy', 'furry'],
            TagCategory::where('name', 'male')->first()->learned_keywords
        );

        // Mapped namespaces learn too.
        $categorizer->resolveForSaving('artist:zyira');
        $this->assertContains('zyira', TagCategory::where('name', 'artist')->first()->learned_keywords);

        // Plain imports teach nothing (resolved by keywords/fallback).
        $categorizer->resolveForSaving('mystery-xyz');
        $this->assertNull(TagCategory::where('name', 'general')->first()->learned_keywords);

        // Too short to be a safe substring keyword.
        $categorizer->resolveForSaving('male:ab');
        $this->assertNotContains('ab', TagCategory::where('name', 'male')->first()->learned_keywords ?? []);
    }

    public function test_learned_keywords_keep_recategorize_stable(): void
    {
        Tag::findOrCreateResolved('male:dog boy');

        // Simulates the admin recategorize pass over the stored short name.
        $again = Tag::findOrCreateResolved('dog boy', true);

        $this->assertSame('dog boy', $again->name);
        $this->assertSame('male', $again->category->name);
    }

    public function test_degenerate_namespaces_never_spawn_categories(): void
    {
        $count = TagCategory::count();

        $this->assertSame('general', app(TagCategorizer::class)->resolveForSaving('12:30')['category']->name);
        $this->assertSame('12:30', app(TagCategorizer::class)->resolveForSaving('12:30')['name']);
        $this->assertSame($count, TagCategory::count());
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

        // Namespaces strip on import: "artist:someone" stores "someone".
        $this->assertSame('someone', $tag->fresh()->name);
        $this->assertSame('artist', $tag->fresh()->category->name);

        // Legacy tag without a category gets backfilled on touch.
        $legacy = Tag::create(['name' => 'leafeon']);
        $this->assertNull($legacy->category_id);

        $found = Tag::findOrCreateCategorized('leafeon');

        $this->assertSame($legacy->id, $found->id);
        $this->assertSame('species', $found->fresh()->category->name);
    }

    public function test_namespaced_twins_merge_into_one_short_tag(): void
    {
        $owner = User::factory()->create();
        $comicA = Comic::factory()->create(['user_id' => $owner->id]);
        $comicB = Comic::factory()->create(['user_id' => $owner->id]);

        $male = Tag::create(['name' => 'male:furry']);
        $female = Tag::create(['name' => 'female:furry']);
        $comicA->tags()->attach([$male->id, $female->id]);
        $comicB->tags()->attach($male->id);

        Tag::findOrCreateResolved('male:furry');
        Tag::findOrCreateResolved('female:furry');

        // First twin is renamed in place; the second merges into it.
        $this->assertSame('furry', Tag::find($male->id)->name);
        $this->assertNull(Tag::find($female->id));

        $survivor = Tag::where('name', 'furry')->firstOrFail();
        $this->assertSame(
            [$comicA->id, $comicB->id],
            $survivor->comics()->orderBy('comics.id')->pluck('comics.id')->all()
        );
        // No duplicated pivot for the comic that had both twins.
        $this->assertSame(1, $comicA->fresh()->tags->where('name', 'furry')->count());
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

        $this->assertSame('artist', $tags['gentsuki']->category->name);
        $this->assertSame('copyright', $tags['pokemon']->category->name);
        $this->assertArrayNotHasKey('artist:gentsuki', $tags->all());
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

    public function test_legacy_tag_without_category_falls_back_to_general(): void
    {
        $tag = Tag::create(['name' => 'mystery-legacy']);
        $this->assertNull($tag->category_id);

        $this->assertSame('general', $tag->fresh()->displayCategory->name);
        $this->assertSame('#252525', $tag->fresh()->displayCategory->background);
    }

    public function test_front_page_pill_uses_fallback_for_uncategorized_tags(): void
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);
        $comic->tags()->attach(Tag::create(['name' => 'mystery-legacy'])->id);

        $this->get('/')
            ->assertOk()
            ->assertSee('mystery-legacy', false)
            ->assertSee('#252525', false);
    }

    public function test_reader_page_groups_tags_by_category(): void
    {
        $user = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $user->id]);
        $comic->tags()->attach([
            Tag::findOrCreateCategorized('artist:zyira')->id,
            Tag::findOrCreateCategorized('male:dog boy')->id,
            Tag::findOrCreateCategorized('language:portuguese')->id,
        ]);

        $response = $this->get("/comics/id/{$comic->id}")->assertOk();

        // Short names stored, grouped under their namespace categories.
        $response->assertSee('Artist:', false);
        $response->assertSee('Male:', false);
        $response->assertSee('Language:', false);
        $response->assertSee('zyira', false);
        $response->assertSee('dog boy', false);
        $response->assertSee('portuguese', false);
        $response->assertDontSee('male:dog boy', false);
        // Full pill style: background + border + FONT color (no "color: ;").
        $response->assertSee('color: #ffe3c2', false);
        $response->assertDontSee('color: ;', false);

        // Artist group renders before the auto-created groups.
        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'Male:'),
            strpos($html, 'Artist:')
        );
    }

    public function test_lookup_flags_auto_created_namespace_categories(): void
    {
        Http::fake([
            'api.e-hentai.org/*' => Http::response([
                'gmetadata' => [[
                    'gid' => 7,
                    'token' => 'bbbbbbbbbb',
                    'title' => 'Gallery',
                    'tags' => ['female:apron'],
                ]],
            ], 200),
        ]);

        $response = $this->getJson('/api/tags/lookup?q='.urlencode('https://e-hentai.org/g/7/bbbbbbbbbb/'))
            ->assertOk();

        $tag = $response->json('galleries.0.tags.0');
        $this->assertSame('female:apron', $tag['full']);
        $this->assertSame('female', $tag['category']['name']);
        $this->assertTrue($tag['auto_create']);
        $this->assertSame('apron', $tag['short_name']);
        // Preview only: nothing persisted by a GET lookup.
        $this->assertNull(TagCategory::where('name', 'female')->first());
        $this->assertNull(Tag::where('name', 'apron')->first());
    }

    public function test_admin_recategorize_strips_and_merges_legacy_tags(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);
        $comic->tags()->attach([
            Tag::create(['name' => 'male:dog boy', 'category_id' => null])->id,
            Tag::create(['name' => 'other:full color', 'category_id' => null])->id,
        ]);

        $this->actingAs(User::factory()->create(['admin_level' => 1]))
            ->post('/admin/tag-categories/recategorize')
            ->assertRedirect(route('admin.tag-categories'))
            ->assertSessionHas('success');

        $names = $comic->fresh()->tags->pluck('name')->all();
        $this->assertContains('dog boy', $names);
        $this->assertContains('full color', $names);
        $this->assertNotContains('male:dog boy', $names);
        $this->assertSame('male', Tag::where('name', 'dog boy')->first()->category->name);
        $this->assertSame('other', Tag::where('name', 'full color')->first()->category->name);
    }

    public function test_hidden_category_tags_vanish_from_public_lists_but_stay_stored(): void
    {
        $owner = User::factory()->create();
        $comic = Comic::factory()->create(['user_id' => $owner->id]);
        $comic->tags()->attach([
            Tag::findOrCreateCategorized('language:portuguese')->id,
            Tag::findOrCreateCategorized('artist:zyira')->id,
        ]);

        TagCategory::where('name', 'language')->firstOrFail()->update(['is_visible' => false]);

        // Front page + reader hide them (no pill, no group header)...
        $this->get('/')
            ->assertOk()
            ->assertSee('zyira', false)
            ->assertDontSee('portuguese', false);

        $this->get("/comics/id/{$comic->id}")
            ->assertOk()
            ->assertSee('Artist:', false)
            ->assertDontSee('Language:', false)
            ->assertDontSee('portuguese', false);

        // ...but data is intact: search still finds the comic and the
        // editor still manages the tag.
        $this->get('/comics/search?tag=portuguese')
            ->assertOk()
            ->assertSee($comic->title, false);

        $this->actingAs($owner)->get("/comics/{$comic->id}/edit")
            ->assertOk()
            ->assertSee('portuguese', false);
    }

    public function test_admin_can_toggle_category_visibility(): void
    {
        $language = TagCategory::where('name', 'language')->first();
        if ($language === null) {
            $language = TagCategory::create([
                'name' => 'language',
                'label' => 'Language',
                'background' => '#111111',
                'border' => '#222222',
                'text_color' => '#333333',
            ]);
        }
        $this->assertTrue($language->fresh()->is_visible);

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->post("/admin/tag-categories/{$language->id}/toggle")
            ->assertRedirect('/');

        $this->actingAs($this->admin())
            ->post("/admin/tag-categories/{$language->id}/toggle")
            ->assertRedirect(route('admin.tag-categories'));

        $this->assertFalse($language->fresh()->is_visible);

        $this->actingAs($this->admin())
            ->post("/admin/tag-categories/{$language->id}/toggle")
            ->assertRedirect(route('admin.tag-categories'));

        $this->assertTrue($language->fresh()->is_visible);
    }

    public function test_admin_can_clear_learned_keywords(): void
    {
        app(TagCategorizer::class)->resolveForSaving('male:dog boy');
        $male = TagCategory::where('name', 'male')->firstOrFail();
        $this->assertSame(['dog boy'], $male->learned_keywords);

        $this->actingAs(User::factory()->create(['admin_level' => 0]))
            ->post("/admin/tag-categories/{$male->id}/clear-learned")
            ->assertRedirect('/');

        $this->actingAs($this->admin())
            ->post("/admin/tag-categories/{$male->id}/clear-learned")
            ->assertRedirect(route('admin.tag-categories'));

        $this->assertSame([], $male->fresh()->learned_keywords);
    }

    public function test_strip_migration_works_without_learned_column(): void
    {
        // Simulates a host that never ran the add-column migration: the
        // strip migration must ensure the column itself (this exact
        // 1054 failed in production).
        Schema::table('tag_categories', function (Blueprint $table) {
            $table->dropColumn('learned_keywords');
        });
        $this->assertFalse(Schema::hasColumn('tag_categories', 'learned_keywords'));

        Tag::create(['name' => 'male:dog boy']);

        $migration = require database_path('migrations/2026_10_17_000001_strip_tag_namespaces.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('tag_categories', 'learned_keywords'));
        $this->assertNull(Tag::where('name', 'male:dog boy')->first());
        $tag = Tag::where('name', 'dog boy')->firstOrFail();
        $this->assertSame('male', $tag->category->name);
        $this->assertSame(['dog boy'], $tag->category->learned_keywords);
    }

    public function test_backfill_migration_works_without_learned_column(): void
    {
        // Simulates a host where the strip migration already ran earlier
        // (recorded, skipped) and the add-column migration is still
        // pending: the backfill must ensure the column itself instead of
        // dying with a 1054 like production did.
        Schema::table('tag_categories', function (Blueprint $table) {
            $table->dropColumn('learned_keywords');
        });
        $this->assertFalse(Schema::hasColumn('tag_categories', 'learned_keywords'));

        // Fixtures bypass learn() (which would need the column): a plain
        // member tag sitting in a non-general category.
        $male = TagCategory::create([
            'name' => 'male',
            'label' => 'Male',
            'background' => '#111111',
            'border' => '#222222',
            'text_color' => '#333333',
            'namespaces' => ['male'],
        ]);
        Tag::create(['name' => 'dog boy', 'category_id' => $male->id]);

        $migration = require database_path('migrations/2026_10_19_000001_backfill_category_keywords.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('tag_categories', 'learned_keywords'));
        $this->assertSame(
            ['dog boy'],
            TagCategory::where('name', 'male')->firstOrFail()->learned_keywords
        );
    }
}
