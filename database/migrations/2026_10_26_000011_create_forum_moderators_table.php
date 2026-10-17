<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-board or forum-wide moderators. forum_category_id null = all.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_moderators')) {
            Schema::create('forum_moderators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('forum_category_id')->nullable()->constrained('forum_categories')->cascadeOnDelete();
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'forum_category_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_moderators');
    }
};
