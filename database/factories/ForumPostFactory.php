<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    public function definition(): array
    {
        return [
            'forum_thread_id' => ForumThread::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(),
            'body_format' => 'markdown',
            'position' => 1,
            'is_approved' => true,
            'edit_count' => 0,
        ];
    }
}
