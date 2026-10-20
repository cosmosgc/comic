<?php

namespace Tests\Feature;

use App\Models\ForumBan;
use App\Models\ForumCategory;
use App\Models\ForumModerator;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumThread;
use App\Models\User;
use Tests\TestCase;

class ForumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guest_can_read_the_forum_index_and_board(): void
    {
        $board = ForumCategory::factory()->board()->create(['name' => 'Geral']);

        $this->get('/forum')->assertOk()->assertSee('Fórum');
        $this->get('/forum/'.$board->slug)->assertOk()->assertSee('Geral');
    }

    public function test_guest_cannot_create_a_thread(): void
    {
        $board = ForumCategory::factory()->board()->create();

        $this->post('/forum/'.$board->slug, ['title' => 'Oi', 'body' => 'Corpo'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('forum_threads', 0);
    }

    public function test_user_can_create_a_thread_with_an_opening_post(): void
    {
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, [
            'title' => 'Meu primeiro tópico',
            'body' => 'Olá pessoal!',
        ])->assertRedirect();

        $thread = ForumThread::first();
        $this->assertNotNull($thread);
        $this->assertSame('Meu primeiro tópico', $thread->title);
        $this->assertDatabaseHas('forum_posts', [
            'forum_thread_id' => $thread->id,
            'position' => 1,
            'body' => 'Olá pessoal!',
        ]);
    }

    public function test_failed_thread_creation_reopens_the_form_with_the_error(): void
    {
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)
            ->from('/forum/'.$board->slug)
            ->post('/forum/'.$board->slug, ['title' => 'Oi', 'body' => ''])
            ->assertRedirect('/forum/'.$board->slug)
            ->assertSessionHasErrors('body');

        $this->actingAs($user)
            ->get('/forum/'.$board->slug)
            ->assertOk()
            ->assertSee('id="new-thread" class="mb-6 rounded-xl border border-zinc-800 p-4"', false);
    }

    public function test_reply_increments_position_and_counts(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $thread = ForumThread::factory()->create(['user_id' => $author->id]);
        ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $author->id, 'position' => 1]);

        $this->actingAs($replier)->post('/forum/threads/'.$thread->id.'/reply', ['body' => 'Boa!'])
            ->assertRedirect();

        $reply = ForumPost::where('position', '>', 1)->first();
        $this->assertNotNull($reply);
        $this->assertSame(2, (int) $reply->position);
        $this->assertSame(1, (int) $thread->fresh()->reply_count);
    }

    public function test_non_owner_cannot_edit_a_post(): void
    {
        $post = ForumPost::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->put('/forum/posts/'.$post->id, ['body' => 'hack'])
            ->assertForbidden();

        $this->assertNotSame('hack', $post->fresh()->body);
    }

    public function test_owner_can_edit_own_post_within_the_window(): void
    {
        $owner = User::factory()->create();
        $post = ForumPost::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->put('/forum/posts/'.$post->id, ['body' => 'editado'])
            ->assertRedirect();

        $this->assertSame('editado', $post->fresh()->body);
        $this->assertSame(1, (int) $post->fresh()->edit_count);
    }

    public function test_reaction_toggles_and_is_unique(): void
    {
        $user = User::factory()->create();
        $post = ForumPost::factory()->create();

        $this->actingAs($user)->postJson('/forum/posts/'.$post->id.'/react', ['reaction' => 'like'])
            ->assertOk()->assertJson(['active' => true, 'count' => 1]);
        $this->assertSame(1, ForumPostReaction::count());

        $this->actingAs($user)->postJson('/forum/posts/'.$post->id.'/react', ['reaction' => 'like'])
            ->assertOk()->assertJson(['active' => false, 'count' => 0]);
        $this->assertSame(0, ForumPostReaction::count());
    }

    public function test_subscription_toggles(): void
    {
        $user = User::factory()->create();
        $thread = ForumThread::factory()->create();

        $this->actingAs($user)->postJson('/forum/threads/'.$thread->id.'/subscribe')
            ->assertOk()->assertJson(['subscribed' => true]);
        $this->assertDatabaseHas('forum_subscriptions', ['user_id' => $user->id, 'forum_thread_id' => $thread->id]);

        $this->actingAs($user)->postJson('/forum/threads/'.$thread->id.'/subscribe')
            ->assertOk()->assertJson(['subscribed' => false]);
        $this->assertDatabaseCount('forum_subscriptions', 0);
    }

    public function test_reporting_a_thread_creates_a_report(): void
    {
        $reporter = User::factory()->create();
        $thread = ForumThread::factory()->create();

        $this->actingAs($reporter)->post('/forum/threads/'.$thread->id.'/report', ['reason' => 'spam'])
            ->assertRedirect();

        $this->assertDatabaseHas('forum_reports', [
            'reporter_id' => $reporter->id,
            'forum_thread_id' => $thread->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    public function test_banned_user_cannot_post(): void
    {
        $user = User::factory()->create();
        $thread = ForumThread::factory()->create();
        ForumBan::create(['user_id' => $user->id, 'banned_by' => User::factory()->create()->id]);

        $this->actingAs($user)->post('/forum/threads/'.$thread->id.'/reply', ['body' => 'oi'])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_posts', 0);
    }

    public function test_approval_mode_hides_new_threads_and_posts(): void
    {
        config(['forum.approval_mode' => 'all']);
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, ['title' => 'Pendente', 'body' => 'segredo'])
            ->assertRedirect();

        $thread = ForumThread::first();
        $this->assertFalse((bool) $thread->is_approved);
        $this->assertFalse((bool) $thread->posts()->first()->is_approved);

        $this->get('/forum/'.$board->slug)->assertOk()->assertDontSee('Pendente');
    }

    public function test_op_can_mark_a_reply_as_solved_and_author_gets_exp(): void
    {
        $op = User::factory()->create();
        $answerer = User::factory()->create(['experience' => 0]);
        $thread = ForumThread::factory()->question()->create(['user_id' => $op->id]);
        ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $op->id, 'position' => 1]);
        $answer = ForumPost::factory()->create(['forum_thread_id' => $thread->id, 'user_id' => $answerer->id, 'position' => 2]);

        $this->actingAs($op)->post('/forum/mark-solved/'.$answer->id)->assertRedirect();

        $this->assertSame($answer->id, $thread->fresh()->solved_post_id);
        $this->assertGreaterThanOrEqual(10, (int) $answerer->fresh()->experience);
    }

    public function test_moderator_can_lock_and_pin_a_thread(): void
    {
        $mod = User::factory()->create();
        $thread = ForumThread::factory()->create();
        ForumModerator::create(['user_id' => $mod->id, 'granted_by' => $mod->id]);

        $this->actingAs($mod)->post('/forum/threads/'.$thread->id.'/lock')->assertRedirect();
        $this->actingAs($mod)->post('/forum/threads/'.$thread->id.'/pin')->assertRedirect();

        $thread->refresh();
        $this->assertTrue((bool) $thread->is_locked);
        $this->assertTrue((bool) $thread->is_pinned);
    }

    public function test_thread_creation_awards_experience(): void
    {
        $user = User::factory()->create(['experience' => 0]);
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, [
            'title' => 'XP aqui',
            'body' => 'conteúdo',
        ])->assertRedirect();

        // 20 for the thread + 5 first-day streak.
        $this->assertGreaterThanOrEqual(20, (int) $user->fresh()->experience);
    }

    public function test_scripts_are_escaped_when_rendering_a_post(): void
    {
        $author = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();
        $thread = ForumThread::factory()->create(['forum_category_id' => $board->id, 'user_id' => $author->id]);
        ForumPost::factory()->create([
            'forum_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => '<script>alert(1)</script> hello',
        ]);

        $response = $this->get('/forum/'.$board->slug.'/'.$thread->slug);

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_forum_uses_the_detached_layout_and_can_exit(): void
    {
        // The forum renders its own navbar (the site's comic search is absent)
        // and always offers a way back to the normal site.
        $this->get('/forum')
            ->assertOk()
            ->assertSee('Voltar ao site')
            ->assertDontSee('Buscar comics...');

        $this->get('/forum/exit')->assertRedirect(route('comics.index'));
    }

    public function test_the_forum_exit_route_is_not_shadowed_by_a_category_slug(): void
    {
        // A board slug of "exit" must not swallow the exit route.
        ForumCategory::factory()->board()->create(['slug' => 'exit']);

        $this->get('/forum/exit')->assertRedirect(route('comics.index'));
    }
}
