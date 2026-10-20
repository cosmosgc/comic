<?php

namespace Tests\Feature;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceReport;
use App\Models\MarketplaceSetting;
use App\Models\User;
use Tests\TestCase;

class AdminMarketplaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_non_admin_is_redirected_from_the_admin_marketplace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/marketplace')->assertRedirect('/');
    }

    public function test_admin_can_view_the_marketplace_screens(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $category = MarketplaceCategory::factory()->create();
        MarketplaceListing::factory()->create([
            'marketplace_category_id' => $category->id,
            'title' => 'Figure do admin',
        ]);

        $this->actingAs($admin)->get('/admin/marketplace')
            ->assertOk()
            ->assertSee('Mercado')
            ->assertSee('Figure do admin');

        $this->actingAs($admin)->get('/admin/marketplace/categories')
            ->assertOk()
            ->assertSee($category->name);

        $this->actingAs($admin)->get('/admin/marketplace/categories/create')
            ->assertOk()
            ->assertSee('Nova categoria');

        $this->actingAs($admin)->get('/admin/marketplace/categories/'.$category->id.'/edit')
            ->assertOk()
            ->assertSee('Editar categoria');

        $this->actingAs($admin)->get('/admin/marketplace/listings')
            ->assertOk()
            ->assertSee('Figure do admin');

        $this->actingAs($admin)->get('/admin/marketplace/moderation')
            ->assertOk()
            ->assertSee('Moderação do Mercado');

        $this->actingAs($admin)->get('/admin/marketplace/settings')
            ->assertOk()
            ->assertSee('Configurações do Mercado');
    }

    public function test_admin_can_approve_feature_hide_and_remove_a_listing(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $seller = User::factory()->create();
        $category = MarketplaceCategory::factory()->create();
        $listing = MarketplaceListing::factory()->pending()->create([
            'marketplace_category_id' => $category->id,
            'user_id' => $seller->id,
        ]);

        $this->actingAs($admin)->post('/admin/marketplace/listings/'.$listing->id.'/action', [
            'action' => 'approve',
        ])->assertRedirect();

        $listing->refresh();
        $this->assertTrue($listing->is_approved);
        $this->assertSame(MarketplaceListing::STATUS_ACTIVE, $listing->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $seller->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($admin)->post('/admin/marketplace/listings/'.$listing->id.'/action', [
            'action' => 'feature',
        ])->assertRedirect();
        $this->assertTrue($listing->fresh()->is_featured);

        $this->actingAs($admin)->post('/admin/marketplace/listings/'.$listing->id.'/action', [
            'action' => 'hide',
        ])->assertRedirect();
        $this->assertSame(MarketplaceListing::STATUS_HIDDEN, $listing->fresh()->status);

        $this->actingAs($admin)->post('/admin/marketplace/listings/'.$listing->id.'/action', [
            'action' => 'remove',
        ])->assertRedirect();
        $this->assertSoftDeleted('marketplace_listings', ['id' => $listing->id]);

        $this->actingAs($admin)->post('/admin/marketplace/listings/'.$listing->id.'/action', [
            'action' => 'restore',
        ])->assertRedirect();
        $this->assertNotSoftDeleted('marketplace_listings', ['id' => $listing->id]);
        $this->assertSame(MarketplaceListing::STATUS_HIDDEN, $listing->fresh()->status);
        $this->assertFalse($listing->fresh()->is_approved);
    }

    public function test_admin_can_resolve_a_report_and_hide_the_listing(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $reporter = User::factory()->create();
        $listing = MarketplaceListing::factory()->create();
        $report = MarketplaceReport::create([
            'reporter_id' => $reporter->id,
            'marketplace_listing_id' => $listing->id,
            'reason' => 'scam',
            'details' => 'Parece golpe.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/admin/marketplace/moderation/'.$report->id.'/resolve', [
            'decision' => 'reviewed',
            'hide_listing' => '1',
        ])->assertRedirect();

        $report->refresh();
        $this->assertSame('reviewed', $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame(MarketplaceListing::STATUS_HIDDEN, $listing->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $reporter->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $section = MarketplaceCategory::factory()->create(['name' => 'Comics']);

        $this->actingAs($admin)->post('/admin/marketplace/categories', [
            'name' => 'Edições raras',
            'parent_id' => $section->id,
            'sort_order' => 3,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.marketplace.categories'));

        $category = MarketplaceCategory::where('name', 'Edições raras')->firstOrFail();
        $this->assertSame('edicoes-raras', $category->slug);
        $this->assertSame($section->id, $category->parent_id);

        $this->actingAs($admin)->put('/admin/marketplace/categories/'.$category->id, [
            'name' => 'Edições raríssimas',
            'parent_id' => $section->id,
            'sort_order' => 5,
            'is_visible' => '0',
        ])->assertRedirect(route('admin.marketplace.categories'));

        $category->refresh();
        $this->assertSame('Edições raríssimas', $category->name);
        $this->assertFalse($category->is_visible);

        $this->actingAs($admin)->post('/admin/marketplace/categories/'.$category->id.'/toggle')
            ->assertRedirect();
        $this->assertTrue($category->fresh()->is_visible);

        $this->actingAs($admin)->delete('/admin/marketplace/categories/'.$category->id)
            ->assertRedirect(route('admin.marketplace.categories'));
        $this->assertDatabaseMissing('marketplace_categories', ['id' => $category->id]);
    }

    public function test_admin_can_run_the_expiry_sweep_on_demand(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $listing = MarketplaceListing::factory()->create([
            'status' => MarketplaceListing::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post('/admin/marketplace/expire')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(MarketplaceListing::STATUS_EXPIRED, $listing->fresh()->status);
    }

    public function test_admin_can_update_marketplace_settings(): void
    {
        config(['marketplace.enabled' => true]);
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->post('/admin/marketplace/settings', [
            'enabled' => '0',
            'require_approval' => '1',
            'default_currency' => 'brl',
            'show_prices' => '1',
            'default_expiry_days' => '45',
            'bump_cooldown_hours' => '12',
            'max_active_listings_per_user' => '10',
            'max_images' => '4',
            'prohibited_words' => 'golpe, pirata',
        ])->assertRedirect(route('admin.marketplace.settings'));

        $this->assertDatabaseHas('marketplace_settings', ['key' => 'enabled', 'value' => '0']);
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'default_currency', 'value' => 'BRL']);
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'max_images', 'value' => '4']);

        $words = json_decode((string) MarketplaceSetting::where('key', 'prohibited_words')->value('value'), true);
        $this->assertSame(['golpe', 'pirata'], $words);

        $this->assertFalse(config('marketplace.enabled'));
        $this->assertTrue(config('marketplace.require_approval'));
        $this->assertSame(45, config('marketplace.default_expiry_days'));
    }
}
