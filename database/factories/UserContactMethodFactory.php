<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserContactMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserContactMethod>
 */
class UserContactMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'whatsapp',
            'label' => null,
            'value' => '551199999'.fake()->numerify('####'),
            'is_public' => true,
            'sort_order' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
