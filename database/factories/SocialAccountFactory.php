<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'discord', 'github']),
            'provider_user_id' => (string) Str::uuid(),
            'nickname' => fake()->userName(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'avatar_url' => null,
        ];
    }
}
