<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumThread>
 */
class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(4, true));

        return [
            'forum_category_id' => ForumCategory::factory()->board(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'type' => 'discussion',
            'is_pinned' => false,
            'is_locked' => false,
            'is_approved' => true,
            'reply_count' => 0,
            'reaction_count' => 0,
            'last_post_at' => now(),
        ];
    }

    public function question(): static
    {
        return $this->state(fn () => ['type' => 'question']);
    }
}
