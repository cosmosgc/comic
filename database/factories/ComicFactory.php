<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Comic>
 */
class ComicFactory extends Factory
{
    protected $model = Comic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->randomNumber(5),
            'user_id' => User::factory(),
        ];
    }
}
