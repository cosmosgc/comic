<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A thread post. The opening post is position = 1; replies follow.
     * body holds the raw (markdown) source; rendering is sanitized.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_posts')) {
            Schema::create('forum_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_thread_id')->constrained('forum_threads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('forum_posts')->nullOnDelete();
                $table->mediumText('body');
                $table->string('body_format')->default('markdown');
                $table->unsignedInteger('position')->default(1);
                $table->boolean('is_approved')->default(true);
                $table->timestamp('edited_at')->nullable();
                $table->unsignedInteger('edit_count')->default(0);
                $table->softDeletes();
                $table->timestamps();

                $table->index(['forum_thread_id', 'position']);
                $table->index('user_id');
                $table->index('parent_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
