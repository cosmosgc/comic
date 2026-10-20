<?php

namespace Tests\Feature;

use App\Models\ForumBan;
use App\Models\ForumCategory;
use App\Models\ForumModerator;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use Tests\TestCase;

class ForumAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_non_admin_is_redirected_from_the_admin_forum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/forum')->assertRedirect('/');
    }

    public function test_admin_can_view_the_dashboard(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        ForumCategory::factory()->board()->create();

        $this->actingAs($admin)->get('/admin/forum')
            ->assertOk()
            ->assertSee('Fórum')
            ->assertSee('forumChart');
    }

    public function test_admin_can_create_and_update_a_category(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $section = ForumCategory::factory()->create();

        $this->actingAs($admin)->post('/admin/forum/categories', [
            'name' => 'Anúncios',
            'parent_id' => $section->id,
            'min_role' => 'member',
            'is_visible' => '1',
        ])->assertRedirect(route('admin.forum.categories'));

        $category = ForumCategory::where('name', 'Anúncios')->first();
        $this->assertNotNull($category);
        $this->assertNotNull($category->slug);

        $this->actingAs($admin)->put('/admin/forum/categories/'.$category->id, [
            'name' => 'Anúncios Importantes',
            'parent_id' => $section->id,
            'min_role' => 'moderator',
            'is_visible' => '1',
        ])->assertRedirect(route('admin.forum.categories'));

        $category->refresh();
        $this->assertSame('Anúncios Importantes', $category->name);
        $this->assertSame('moderator', $category->min_role);
    }

    public function test_admin_can_toggle_category_visibility(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $board = ForumCategory::factory()->board()->create(['is_visible' => true]);

        $this->actingAs($admin)->post('/admin/forum/categories/'.$board->id.'/toggle', ['field' => 'is_visible'])
            ->assertRedirect();

        $this->assertFalse((bool) $board->fresh()->is_visible);
    }

    public function test_admin_can_grant_and_revoke_moderation(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $user = User::factory()->create();

        $this->actingAs($admin)->post('/admin/forum/moderators', ['user_id' => $user->id])
            ->assertRedirect();
        $this->assertDatabaseHas('forum_moderators', ['user_id' => $user->id]);

        $moderator = ForumModerator::first();
        $this->actingAs($admin)->delete('/admin/forum/moderators/'.$moderator->id)
            ->assertRedirect();
        $this->assertDatabaseCount('forum_moderators', 0);
    }

    public function test_admin_can_ban_a_user_and_enforcement_applies(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $user = User::factory()->create();
        $thread = ForumThread::factory()->create();

        $this->actingAs($admin)->post('/admin/forum/bans', [
            'user_id' => $user->id,
            'reason' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_bans', ['user_id' => $user->id]);

        $this->actingAs($user)->post('/forum/threads/'.$thread->id.'/reply', ['body' => 'oi'])
            ->assertForbidden();

        $ban = ForumBan::first();
        $this->actingAs($admin)->delete('/admin/forum/bans/'.$ban->id)->assertRedirect();
        $this->assertDatabaseCount('forum_bans', 0);
    }

    public function test_admin_settings_are_persisted_and_applied(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->post('/admin/forum/settings', [
            'enabled' => '1',
            'approval_mode' => 'first_post',
            'edit_grace_minutes' => 15,
            'min_account_age_hours' => 2,
            'max_attachments' => 3,
            'allowed_mime' => 'image/png, image/jpeg',
            'posts_per_page' => 20,
            'hot_window_days' => 10,
            'auto_lock_days' => 30,
            'prohibited_words' => 'badword, outro',
        ])->assertRedirect(route('admin.forum.settings'));

        $this->assertDatabaseHas('forum_settings', ['key' => 'approval_mode', 'value' => 'first_post']);
        $this->assertSame('first_post', config('forum.approval_mode'));
        $this->assertSame(15, (int) config('forum.edit_grace_minutes'));
        $this->assertSame(['image/png', 'image/jpeg'], config('forum.allowed_mime'));
        $this->assertSame(['badword', 'outro'], config('forum.prohibited_words'));
    }

    public function test_prohibited_words_block_a_thread(): void
    {
        config(['forum.prohibited_words' => ['proibido']]);
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, [
            'title' => 'Assunto proibido',
            'body' => 'texto',
        ])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('forum_threads', 0);
    }

    public function test_admin_can_edit_a_thread_and_move_it_to_another_board(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $currentBoard = ForumCategory::factory()->board()->create();
        $newBoard = ForumCategory::factory()->board()->create();
        $thread = ForumThread::factory()->create([
            'forum_category_id' => $currentBoard->id,
            'title' => 'Título antigo',
            'slug' => 'titulo-antigo',
        ]);

        $this->actingAs($admin)->get('/admin/forum/threads/'.$thread->id.'/edit')
            ->assertOk()
            ->assertSee('Título antigo')
            ->assertSee('name="banner_position_x"', false);

        $this->actingAs($admin)->put('/admin/forum/threads/'.$thread->id, [
            'title' => 'Título novo',
            'forum_category_id' => $newBoard->id,
            'type' => 'question',
            'banner_position_x' => 30,
            'banner_position_y' => 70,
            'is_pinned' => '1',
            'is_locked' => '1',
            'is_approved' => '1',
        ])->assertRedirect(route('admin.forum.threads'));

        $thread->refresh();
        $this->assertSame('Título novo', $thread->title);
        $this->assertSame('titulo-novo', $thread->slug);
        $this->assertSame($newBoard->id, (int) $thread->forum_category_id);
        $this->assertSame('question', $thread->type);
        $this->assertTrue($thread->is_pinned);
        $this->assertTrue($thread->is_locked);
        $this->assertSame(30, $thread->banner_position_x);
        $this->assertSame(70, $thread->banner_position_y);
        $this->assertSame(1, (int) $newBoard->fresh()->thread_count);
        $this->assertSame(0, (int) $currentBoard->fresh()->thread_count);
    }

    public function test_admin_can_soft_delete_and_restore_a_thread(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $thread = ForumThread::factory()->create(['title' => 'Tópico removível']);

        $this->actingAs($admin)->delete('/admin/forum/threads/'.$thread->id)
            ->assertRedirect();
        $this->assertSoftDeleted('forum_threads', ['id' => $thread->id]);

        $this->actingAs($admin)->get('/admin/forum/threads?status=removed')
            ->assertOk()
            ->assertSee('Tópico removível');

        $this->actingAs($admin)->post('/admin/forum/threads/'.$thread->id.'/restore')
            ->assertRedirect();
        $this->assertNotSoftDeleted('forum_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_resolve_a_report(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $reporter = User::factory()->create();
        $thread = ForumThread::factory()->create();
        $report = ForumReport::create([
            'reporter_id' => $reporter->id,
            'forum_thread_id' => $thread->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($admin)->post('/admin/forum/moderation/'.$report->id.'/resolve', ['status' => 'reviewed'])
            ->assertRedirect();

        $this->assertSame('reviewed', $report->fresh()->status);
        $this->assertSame($admin->id, (int) $report->fresh()->reviewed_by);
    }
}
