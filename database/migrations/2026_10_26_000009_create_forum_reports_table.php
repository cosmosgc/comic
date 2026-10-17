<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reports against a thread or a single post, reviewed in the admin
     * moderation queue.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_reports')) {
            Schema::create('forum_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('forum_thread_id')->nullable()->constrained('forum_threads')->cascadeOnDelete();
                $table->foreignId('forum_post_id')->nullable()->constrained('forum_posts')->cascadeOnDelete();
                $table->string('reason');
                $table->text('details')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_reports');
    }
};
