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
