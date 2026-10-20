<?php

namespace Database\Factories;

use App\Models\MarketplaceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceCategory>
 */
class MarketplaceCategoryFactory extends Factory
{
    protected $model = MarketplaceCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'icon' => '📦',
            'color' => null,
            'sort_order' => 0,
            'is_visible' => true,
            'listing_count' => 0,
        ];
    }

    public function child(?MarketplaceCategory $parent = null): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent?->id ?? MarketplaceCategory::factory(),
        ]);
    }
}
