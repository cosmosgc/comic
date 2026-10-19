<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPoll;
use App\Models\ForumPollOption;
use App\Models\ForumThread;
use App\Models\User;
use Tests\TestCase;

class ForumPollTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    private function category(): ForumCategory
    {
        return ForumCategory::create([
            'name' => 'Geral',
            'slug' => 'geral',
            'min_role' => 'member',
            'is_visible' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pollThread(ForumCategory $category, User $author, array $overrides = [], bool $multiple = false): ForumThread
    {
        $thread = ForumThread::create(array_merge([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'title' => 'Enquete de teste',
            'slug' => 'enquete-de-teste',
            'type' => 'poll',
            'is_approved' => true,
            'last_post_at' => now(),
        ], $overrides));

        $poll = ForumPoll::create([
            'forum_thread_id' => $thread->id,
            'question' => 'Qual o melhor?',
            'multiple' => $multiple,
        ]);

        ForumPollOption::create(['forum_poll_id' => $poll->id, 'label' => 'Opção A', 'position' => 1]);
        ForumPollOption::create(['forum_poll_id' => $poll->id, 'label' => 'Opção B', 'position' => 2]);

        return $thread->fresh();
    }

    public function test_creating_a_poll_thread_stores_the_question_and_distinct_options(): void
    {
        $user = User::factory()->create();
        $category = $this->category();

        $this->actingAs($user)->post(route('forum.threads.store', ['categorySlug' => $category->slug]), [
            'title' => 'Nova enquete',
            'body' => 'Vote abaixo.',
            'type' => 'poll',
            'poll_question' => 'Qual o melhor?',
            'poll_options' => ['A', 'B', 'A', ''],
        ])->assertRedirect();

        $thread = ForumThread::where('title', 'Nova enquete')->firstOrFail();

        $this->assertTrue($thread->isPoll());
        $this->assertSame('Qual o melhor?', $thread->poll->question);
        $this->assertSame(['A', 'B'], $thread->poll->options->pluck('label')->all());
    }

    public function test_poll_needs_at_least_two_distinct_options(): void
    {
        $user = User::factory()->create();
        $category = $this->category();

        $this->actingAs($user)->post(route('forum.threads.store', ['categorySlug' => $category->slug]), [
            'title' => 'Inválida',
            'body' => 'Vote.',
            'type' => 'poll',
            'poll_question' => 'Pergunta?',
            'poll_options' => ['A', 'A', ''],
        ])->assertSessionHasErrors('poll_options');

        $this->assertDatabaseMissing('forum_threads', ['title' => 'Inválida']);
    }

    public function test_member_votes_and_can_change_a_single_choice_vote(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        $thread = $this->pollThread($this->category(), $author);
        $options = $thread->poll->options;

        $this->actingAs($voter)
            ->post(route('forum.polls.vote', $thread), ['options' => [$options[0]->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('forum_poll_votes', [
            'forum_poll_option_id' => $options[0]->id,
            'user_id' => $voter->id,
        ]);
        $this->assertSame(1, $options[0]->fresh()->votes_count);

        $this->actingAs($voter)
            ->post(route('forum.polls.vote', $thread), ['options' => [$options[1]->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('forum_poll_votes', [
            'forum_poll_option_id' => $options[0]->id,
            'user_id' => $voter->id,
        ]);
        $this->assertDatabaseHas('forum_poll_votes', [
            'forum_poll_option_id' => $options[1]->id,
            'user_id' => $voter->id,
        ]);
        $this->assertSame(1, $options[1]->fresh()->votes_count);
        $this->assertSame(0, $options[0]->fresh()->votes_count);
    }

    public function test_multiple_choice_poll_accepts_more_than_one_option(): void
    {
        $author = User::factory()->create();
        $voter = User::factory()->create();
        $thread = $this->pollThread($this->category(), $author, [], true);
        $options = $thread->poll->options;

        $this->actingAs($voter)
            ->post(route('forum.polls.vote', $thread), [
                'options' => [$options[0]->id, $options[1]->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('forum_poll_votes', 2);
    }

    public function test_single_choice_poll_rejects_two_options(): void
    {
        $thread = $this->pollThread($this->category(), User::factory()->create());
        $options = $thread->poll->options;

        $this->actingAs(User::factory()->create())
            ->post(route('forum.polls.vote', $thread), [
                'options' => [$options[0]->id, $options[1]->id],
            ])
            ->assertSessionHasErrors('options');

        $this->assertDatabaseCount('forum_poll_votes', 0);
    }

    public function test_an_option_from_another_poll_is_rejected(): void
    {
        $author = User::factory()->create();
        $category = $this->category();
        $thread = $this->pollThread($category, $author);
        $other = $this->pollThread($category, $author, [
            'title' => 'Outra enquete',
            'slug' => 'outra-enquete',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('forum.polls.vote', $thread), [
                'options' => [$other->poll->options[0]->id],
            ])
            ->assertSessionHasErrors('options');

        $this->assertDatabaseCount('forum_poll_votes', 0);
    }

    public function test_closed_poll_rejects_votes(): void
    {
        $thread = $this->pollThread($this->category(), User::factory()->create());
        $thread->poll->update(['closes_at' => now()->subMinute()]);

        $this->actingAs(User::factory()->create())
            ->post(route('forum.polls.vote', $thread), [
                'options' => [$thread->poll->options[0]->id],
            ])
            ->assertSessionHasErrors('poll');

        $this->assertDatabaseCount('forum_poll_votes', 0);
    }

    public function test_guest_cannot_vote(): void
    {
        $thread = $this->pollThread($this->category(), User::factory()->create());

        $this->post(route('forum.polls.vote', $thread), [
            'options' => [$thread->poll->options[0]->id],
        ])->assertRedirect('/login');
    }

    public function test_thread_page_renders_the_poll(): void
    {
        $category = $this->category();
        $thread = $this->pollThread($category, User::factory()->create());

        $this->get(route('forum.thread', [
            'categorySlug' => $category->slug,
            'threadSlug' => $thread->slug,
        ]))
            ->assertOk()
            ->assertSee('Qual o melhor?')
            ->assertSee('Opção A')
            ->assertSee('Opção B');
    }
}
