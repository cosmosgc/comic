<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum-scoped tags (the global `tags` table is comic-scoped) plus
     * the thread pivot.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_tags')) {
            Schema::create('forum_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('color')->nullable();
                $table->unsignedInteger('use_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('forum_tag_thread')) {
            Schema::create('forum_tag_thread', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_thread_id')->constrained('forum_threads')->cascadeOnDelete();
                $table->foreignId('forum_tag_id')->constrained('forum_tags')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['forum_thread_id', 'forum_tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_tag_thread');
        Schema::dropIfExists('forum_tags');
    }
};
