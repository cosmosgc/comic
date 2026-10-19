<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class MuteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_toggle_mutes_and_unmutes(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('users.mute', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('mutes', [
            'user_id' => $actor->id,
            'muted_id' => $target->id,
        ]);

        $this->actingAs($actor)
            ->post(route('users.mute', $target))
            ->assertRedirect();

        $this->assertDatabaseMissing('mutes', [
            'user_id' => $actor->id,
            'muted_id' => $target->id,
        ]);
    }

    public function test_toggle_returns_json_when_requested(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('users.mute', $target))
            ->assertOk()
            ->assertJson(['muted' => true]);

        $this->actingAs($actor)
            ->postJson(route('users.mute', $target))
            ->assertOk()
            ->assertJson(['muted' => false]);
    }

    public function test_cannot_mute_self(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('users.mute', $actor))
            ->assertStatus(422);
    }

    public function test_guest_cannot_mute(): void
    {
        $target = User::factory()->create();

        $this->post(route('users.mute', $target))->assertRedirect('/login');
    }

    public function test_muted_authors_are_hidden_from_the_feed(): void
    {
        $actor = User::factory()->create();
        $muted = User::factory()->create();
        Post::factory()->create(['author_id' => $muted->id, 'text' => 'Texto do silenciado']);
        Post::factory()->create(['author_id' => User::factory()->create()->id, 'text' => 'Texto visível']);

        $this->actingAs($actor)->get('/posts')->assertSee('Texto visível');

        $actor->mutedUsers()->attach($muted->id);

        $this->actingAs($actor)
            ->get('/posts')
            ->assertDontSee('Texto do silenciado')
            ->assertSee('Texto visível');
    }

    public function test_muted_author_replies_are_hidden_from_a_thread(): void
    {
        $actor = User::factory()->create();
        $parent = Post::factory()->create(['author_id' => User::factory()->create()->id]);
        $muted = User::factory()->create();
        Post::factory()->create([
            'author_id' => $muted->id,
            'parent_id' => $parent->id,
            'text' => 'Resposta do silenciado',
        ]);
        Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'parent_id' => $parent->id,
            'text' => 'Resposta visível',
        ]);

        $actor->mutedUsers()->attach($muted->id);

        $this->actingAs($actor)
            ->get(route('posts.show', $parent))
            ->assertDontSee('Resposta do silenciado')
            ->assertSee('Resposta visível');
    }
}
