<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Tests\TestCase;

class PostReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_guest_cannot_report(): void
    {
        $post = Post::factory()->create();

        $this->post("/posts/{$post->id}/report", ['reason' => 'spam'])->assertRedirect('/login');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_user_can_report_a_post_once(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($reporter)->post("/posts/{$post->id}/report", [
            'reason' => 'spam',
            'details' => 'Looks fishy',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'post_id' => $post->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);

        // A second open report by the same user is ignored.
        $this->actingAs($reporter)->post("/posts/{$post->id}/report", ['reason' => 'abuse']);
        $this->assertSame(1, Report::count());
    }

    public function test_user_cannot_report_own_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)->post("/posts/{$post->id}/report", ['reason' => 'spam'])->assertForbidden();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        $post = Post::factory()->create();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post("/posts/{$post->id}/report", ['reason' => 'nonsense'])
            ->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_admin_can_view_and_resolve_reports(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $reporter = User::factory()->create();
        $post = Post::factory()->create();
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'post_id' => $post->id,
            'reason' => 'abuse',
        ]);

        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertSee('Abuso');

        $this->actingAs($admin)->post("/admin/reports/{$report->id}/resolve", ['status' => 'reviewed'])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'reviewed', 'reviewed_by' => $admin->id]);
    }

    public function test_non_admin_cannot_access_the_queue(): void
    {
        $user = User::factory()->create(['admin_level' => 0]);

        $this->actingAs($user)->get('/admin/reports')->assertRedirect('/');
    }
}
