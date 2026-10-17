<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A discussion thread. The opening post lives in forum_posts
     * (position = 1), so there is no body column here.
     *
     * solved_post_id references forum_posts, which is created in the
     * next migration; it is left unconstrained to avoid a circular FK.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_threads')) {
            Schema::create('forum_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_category_id')->constrained('forum_categories')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->string('type')->default('discussion');
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->boolean('is_approved')->default(true);
                $table->softDeletes();
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('reply_count')->default(0);
                $table->unsignedInteger('reaction_count')->default(0);
                $table->timestamp('last_post_at')->nullable();
                $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('solved_post_id')->nullable();
                $table->timestamps();

                $table->unique(['forum_category_id', 'slug']);
                $table->index(['forum_category_id', 'is_pinned', 'last_post_at']);
                $table->index('user_id');
                $table->index('solved_post_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_threads');
    }
};
