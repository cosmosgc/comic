<?php

namespace Tests\Feature;

use App\Mail\ForumDigestMail;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForumDigestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    private function recentThread(): ForumThread
    {
        $category = ForumCategory::create([
            'name' => 'Geral',
            'slug' => 'geral',
            'min_role' => 'member',
            'is_visible' => true,
        ]);

        return ForumThread::create([
            'forum_category_id' => $category->id,
            'user_id' => User::factory()->create()->id,
            'title' => 'Tópico recente',
            'slug' => 'topico-recente',
            'type' => 'discussion',
            'is_approved' => true,
            'last_post_at' => now(),
            'reply_count' => 2,
        ]);
    }

    public function test_opted_in_members_receive_the_digest(): void
    {
        Mail::fake();
        $this->recentThread();

        $optedIn = User::factory()->create(['forum_digest' => true]);
        $optedOut = User::factory()->create(['forum_digest' => false]);

        $this->artisan('forum:digest')->assertSuccessful();

        Mail::assertSent(ForumDigestMail::class, fn (ForumDigestMail $mail) => $mail->hasTo($optedIn->email));
        Mail::assertNotSent(ForumDigestMail::class, fn (ForumDigestMail $mail) => $mail->hasTo($optedOut->email));
    }

    public function test_no_mail_without_recent_activity(): void
    {
        Mail::fake();
        $this->recentThread()->update(['last_post_at' => now()->subDays(30)]);
        User::factory()->create(['forum_digest' => true]);

        $this->artisan('forum:digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_disabled_forum_sends_nothing(): void
    {
        Mail::fake();
        config(['forum.enabled' => false]);
        $this->recentThread();
        User::factory()->create(['forum_digest' => true]);

        $this->artisan('forum:digest')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
