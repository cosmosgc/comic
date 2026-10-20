<?php

namespace Tests\Feature;

use App\Models\ForumAttachment;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ForumAttachmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_reply_attachment_is_written_to_public_storage(): void
    {
        $user = User::factory()->create();
        $thread = ForumThread::factory()->create();

        $this->actingAs($user)->post('/forum/threads/'.$thread->id.'/reply', [
            'body' => 'Com imagem',
            'attachments' => [UploadedFile::fake()->image('HB.jpg', 40, 30)],
        ])->assertRedirect();

        $attachment = ForumAttachment::firstOrFail();

        $this->assertStringStartsWith('forum/', $attachment->path);
        $this->assertFileExists(public_path('storage/'.$attachment->path));
        $this->assertFileDoesNotExist(storage_path('app/public/'.$attachment->path));
        $this->assertStringContainsString('/storage/'.$attachment->path, $attachment->url());
        $this->assertSame(40, (int) $attachment->image_width);
        $this->assertSame(30, (int) $attachment->image_height);

        @unlink(public_path('storage/'.$attachment->path));
    }

    public function test_opening_post_attachment_is_written_to_public_storage(): void
    {
        $user = User::factory()->create();
        $board = ForumCategory::factory()->board()->create();

        $this->actingAs($user)->post('/forum/'.$board->slug, [
            'title' => 'Tópico com anexo',
            'body' => 'Imagem no primeiro post',
            'attachments' => [UploadedFile::fake()->image('capa.jpg', 60, 40)],
        ])->assertRedirect();

        $attachment = ForumAttachment::firstOrFail();
        $this->assertFileExists(public_path('storage/'.$attachment->path));

        @unlink(public_path('storage/'.$attachment->path));
    }
}
