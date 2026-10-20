<?php

namespace Database\Factories;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceListing>
 */
class MarketplaceListingFactory extends Factory
{
    protected $model = MarketplaceListing::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(4, true));

        return [
            'user_id' => User::factory(),
            'marketplace_category_id' => MarketplaceCategory::factory(),
            'kind' => MarketplaceListing::KIND_PHYSICAL,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'condition' => 'good',
            'listing_type' => 'sale',
            'price' => fake()->randomFloat(2, 10, 500),
            'currency' => 'BRL',
            'price_negotiable' => false,
            'quantity' => 1,
            'location_city' => 'São Paulo',
            'location_region' => 'SP',
            'location_country' => 'BR',
            'shipping' => 'arrange',
            'status' => MarketplaceListing::STATUS_ACTIVE,
            'is_featured' => false,
            'is_approved' => true,
            'contact_mode' => 'in_app',
            'published_at' => now(),
            'expires_at' => now()->addDays(60),
        ];
    }

    public function comic(): static
    {
        return $this->state(fn () => [
            'kind' => MarketplaceListing::KIND_COMIC,
            'condition' => 'na',
            'shipping' => 'digital',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => MarketplaceListing::STATUS_DRAFT,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['is_approved' => false]);
    }

    public function sold(): static
    {
        return $this->state(fn () => [
            'status' => MarketplaceListing::STATUS_SOLD,
            'sold_at' => now(),
        ]);
    }
}
