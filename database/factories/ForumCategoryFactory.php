<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumCategory>
 */
class ForumCategoryFactory extends Factory
{
    protected $model = ForumCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'icon' => '💬',
            'color' => null,
            'sort_order' => 0,
            'is_visible' => true,
            'is_locked' => false,
            'min_role' => 'member',
        ];
    }

    /**
     * A board (child) of the given section.
     */
    public function board(?ForumCategory $section = null): static
    {
        return $this->state(fn () => [
            'parent_id' => $section?->id ?? ForumCategory::factory(),
        ]);
    }
}
