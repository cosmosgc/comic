<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two levels of forum structure in one self-referential table:
     * sections (parent_id = null) and boards (child of a section).
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_categories')) {
            Schema::create('forum_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('forum_categories')->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->string('color')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_locked')->default(false);
                $table->string('min_role')->default('member');
                $table->unsignedInteger('thread_count')->default(0);
                $table->unsignedInteger('post_count')->default(0);
                $table->timestamps();

                $table->index(['parent_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_categories');
    }
};
