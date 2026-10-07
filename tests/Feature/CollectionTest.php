<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Comic;
use App\Models\User;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        // Each test boots a fresh app (fresh :memory: database).
        $this->artisan('migrate');
    }

    public function test_store_assigns_owner_to_creator(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/collections', [
            'name' => 'My stash',
            'description' => 'Good stuff',
        ]);

        $response->assertRedirect(route('collections.create'));
        $this->assertDatabaseHas('collections', [
            'name' => 'My stash',
            'user_id' => $user->id,
            'is_public' => true,
            'is_favorites' => false,
        ]);
    }

    public function test_owner_can_add_and_remove_comics(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mine', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $comic = Comic::factory()->create();

        $this->actingAs($user)
            ->postJson("/collections/{$collection->id}/comics/{$comic->id}")
            ->assertOk()
            ->assertJson(['added' => true, 'count' => 1]);

        // Adding twice stays idempotent.
        $this->actingAs($user)
            ->postJson("/collections/{$collection->id}/comics/{$comic->id}")
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->actingAs($user)
            ->deleteJson("/collections/{$collection->id}/comics/{$comic->id}")
            ->assertOk()
            ->assertJson(['removed' => true, 'count' => 0]);
    }

    public function test_stranger_cannot_change_collection(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mine', 'user_id' => $owner->id, 'is_public' => true,
        ]);
        $comic = Comic::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/collections/{$collection->id}/comics/{$comic->id}")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete("/collections/{$collection->id}")
            ->assertForbidden();
    }

    public function test_private_collection_hidden_from_strangers(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Secret', 'user_id' => $owner->id, 'is_public' => false,
        ]);

        $this->actingAs($stranger)->get("/collections/{$collection->id}")->assertForbidden();
        $this->actingAs($owner)->get("/collections/{$collection->id}")->assertOk();
    }

    public function test_favorites_autocreated_on_register_and_toggleable(): void
    {
        $this->post('/register', [
            'name' => 'Newbie',
            'email' => 'newbie@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'newbie@example.com')->firstOrFail();
        $favorites = Collection::where('user_id', $user->id)->where('is_favorites', true)->first();
        $this->assertNotNull($favorites);
        $this->assertFalse((bool) $favorites->is_public);

        // Second registration path never duplicates favorites.
        $this->assertSame(1, Collection::where('user_id', $user->id)->where('is_favorites', true)->count());

        $comic = Comic::factory()->create();

        $this->actingAs($user)->postJson("/collections/favorite/{$comic->id}")
            ->assertOk()->assertJson(['favorited' => true, 'count' => 1]);

        $this->actingAs($user)->postJson("/collections/favorite/{$comic->id}")
            ->assertOk()->assertJson(['favorited' => false, 'count' => 0]);
    }

    public function test_relations_carry_no_default_order(): void
    {
        // orderBy('pivot_order') on a BelongsToMany leaks into exists() /
        // first() / count() subqueries where the alias isn't selected:
        // MySQL throws 1054 (SQLite silently tolerates it, so only this
        // structural assertion guards the regression on every driver).
        $collectionOrders = (new Collection)->comics()->getQuery()->getQuery()->orders;
        $comicOrders = (new Comic)->collections()->getQuery()->getQuery()->orders;

        $this->assertEmpty($collectionOrders);
        $this->assertEmpty($comicOrders);
    }

    public function test_ordered_comics_respects_manual_sort(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create(['name' => 'Sorted', 'user_id' => $user->id]);
        $first = Comic::factory()->create();
        $second = Comic::factory()->create();
        $collection->comics()->attach($second->id, ['order' => 2]);
        $collection->comics()->attach($first->id, ['order' => 1]);

        $ordered = $collection->orderedComics()->pluck('comics.id')->all();

        $this->assertSame([$first->id, $second->id], $ordered);
    }

    public function test_show_paginates_comics(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Big', 'user_id' => $user->id, 'is_public' => true,
        ]);
        foreach (range(1, 15) as $i) {
            $comic = Comic::factory()->create([
                'title' => sprintf('Paginated Comic %02d', $i),
                'slug' => 'paginated-comic-'.$i.'-'.uniqid(),
            ]);
            $collection->comics()->attach($comic->id, ['order' => $i]);
        }

        $pageOne = $this->get("/collections/{$collection->id}");
        $pageOne->assertOk();
        // 12 per page in manual order: 01–12 here, 13–15 on page 2.
        $pageOne->assertSee('Paginated Comic 01');
        $pageOne->assertDontSee('Paginated Comic 15');

        $pageTwo = $this->get("/collections/{$collection->id}?page=2");
        $pageTwo->assertOk();
        $pageTwo->assertSee('Paginated Comic 15');
        $pageTwo->assertDontSee('Paginated Comic 01');
    }

    public function test_show_search_filters_by_title_and_author(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mixed', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $wanted = Comic::factory()->create(['title' => 'Zebra Chronicles Alpha']);
        $unwanted = Comic::factory()->create(['title' => 'Totally Different Beta']);
        $collection->comics()->attach([$wanted->id, $unwanted->id]);

        $response = $this->get("/collections/{$collection->id}?q=Zebra");

        $response->assertOk();
        $response->assertSee('Zebra Chronicles Alpha');
        $response->assertDontSee('Totally Different Beta');
    }

    public function test_guests_cannot_reach_write_pages(): void
    {
        $collection = Collection::create(['name' => 'Open', 'is_public' => true]);

        $this->get('/collections/create')->assertRedirect('/login');
        $this->post('/collections', ['name' => 'Nope'])->assertRedirect('/login');
        $this->get("/collections/{$collection->id}/edit")->assertRedirect('/login');
        $this->put("/collections/{$collection->id}", ['name' => 'Nope'])->assertRedirect('/login');
    }

    public function test_edit_button_only_for_writers(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mine', 'user_id' => $owner->id, 'is_public' => true,
        ]);

        $this->actingAs($owner)->get("/collections/{$collection->id}")
            ->assertOk()->assertSee('Edit collection');
        $this->actingAs($stranger)->get("/collections/{$collection->id}")
            ->assertOk()->assertDontSee('Edit collection');
    }

    public function test_update_without_comics_key_preserves_attachments(): void
    {
        // Regression: the edit form once submitted no comics field, and
        // sync(null) wiped the whole collection.
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Keep', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $comic = Comic::factory()->create();
        $collection->comics()->attach($comic->id, ['order' => 1]);

        $this->actingAs($user)->put("/collections/{$collection->id}", [
            'name' => 'Renamed',
        ])->assertRedirect(route('collections.edit', $collection));

        $this->assertSame(1, $collection->fresh()->comics()->count());
    }

    public function test_update_with_comics_key_syncs_selection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Sync', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $keep = Comic::factory()->create();
        $drop = Comic::factory()->create();
        $add = Comic::factory()->create();
        $collection->comics()->attach([$keep->id, $drop->id]);

        $this->actingAs($user)->put("/collections/{$collection->id}", [
            'name' => 'Sync',
            'comics' => [$keep->id, $add->id],
        ])->assertRedirect(route('collections.edit', $collection));

        $ids = $collection->fresh()->comics()->pluck('comics.id')->sort()->values()->all();
        $expected = collect([$keep->id, $add->id])->sort()->values()->all();
        $this->assertSame($expected, $ids);
    }

    public function test_edit_page_access_rules(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mine', 'user_id' => $owner->id, 'is_public' => true,
        ]);

        $this->get("/collections/{$collection->id}/edit")->assertRedirect('/login');
        $this->actingAs($stranger)->get("/collections/{$collection->id}/edit")->assertForbidden();
        $this->actingAs($owner)->get("/collections/{$collection->id}/edit")->assertOk();
    }

    public function test_show_header_displays_first_comic_cover(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Covered', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $first = Comic::factory()->create(['image_path' => 'covers/first.jpg']);
        $second = Comic::factory()->create(['image_path' => 'covers/second.jpg']);
        $collection->comics()->attach([$second->id => ['order' => 2], $first->id => ['order' => 1]]);

        $response = $this->get("/collections/{$collection->id}");

        $response->assertOk();
        $response->assertSee('covers/first.jpg', false);
    }

    public function test_profile_collections_tab_shows_covers(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Mine', 'user_id' => $user->id, 'is_public' => true,
        ]);
        $comic = Comic::factory()->create(['image_path' => 'covers/mine.jpg']);
        $collection->comics()->attach($comic->id, ['order' => 1]);

        $response = $this->actingAs($user)->get('/profile?tab=collections');

        $response->assertOk();
        $response->assertSee('covers/mine.jpg', false);
    }

    public function test_empty_collection_renders_without_cover(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Empty', 'user_id' => $user->id, 'is_public' => true,
        ]);

        $this->get("/collections/{$collection->id}")->assertOk();
    }

    public function test_owner_can_delete_collection(): void
    {
        $user = User::factory()->create();
        $collection = Collection::create([
            'name' => 'Temp', 'user_id' => $user->id, 'is_public' => true,
        ]);

        $this->actingAs($user)->delete("/collections/{$collection->id}")
            ->assertRedirect(route('collections.index'));
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }
}
