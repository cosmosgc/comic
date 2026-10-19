<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\User;
use Tests\TestCase;

class ForumCategoryOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    private function category(string $name, string $slug, int $order): ForumCategory
    {
        return ForumCategory::create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $order,
        ]);
    }

    public function test_admin_reorders_categories(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $a = $this->category('A', 'a', 0);
        $b = $this->category('B', 'b', 1);
        $c = $this->category('C', 'c', 2);

        $this->actingAs($admin)
            ->postJson(route('admin.forum.categories.reorder'), [
                'order' => [$c->id, $a->id, $b->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_non_admin_cannot_reorder_categories(): void
    {
        $category = $this->category('A', 'a', 0);

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.forum.categories.reorder'), ['order' => [$category->id]])
            ->assertRedirect('/');
    }

    public function test_unknown_ids_are_rejected(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)
            ->postJson(route('admin.forum.categories.reorder'), ['order' => [999]])
            ->assertStatus(422);
    }
}
