<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user unread tracking for a thread ("new posts" dots).
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_thread_reads')) {
            Schema::create('forum_thread_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('forum_thread_id')->constrained('forum_threads')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_post_id')->nullable();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'forum_thread_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_thread_reads');
    }
};
