<?php

namespace Tests\Feature;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use Tests\TestCase;

class MarketplaceExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_the_command_expires_listings_and_notifies_the_seller(): void
    {
        $seller = User::factory()->create();
        $category = MarketplaceCategory::factory()->create();

        $expired = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $category->id,
            'user_id' => $seller->id,
            'status' => MarketplaceListing::STATUS_ACTIVE,
            'expires_at' => now()->subHour(),
        ]);
        $future = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $category->id,
            'user_id' => $seller->id,
            'status' => MarketplaceListing::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('marketplace:expire')->assertSuccessful();

        $this->assertSame(MarketplaceListing::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertSame(MarketplaceListing::STATUS_ACTIVE, $future->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $seller->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_dry_run_reports_without_changing_listings(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'status' => MarketplaceListing::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('marketplace:expire', ['--dry-run' => true])
            ->expectsOutputToContain('1 anúncio(s) expirariam.')
            ->assertSuccessful();

        $this->assertSame(MarketplaceListing::STATUS_ACTIVE, $listing->fresh()->status);
        $this->assertSame(0, $listing->seller->notifications()->count());
    }
}
