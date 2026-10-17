<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per user + post + reaction kind (like, laugh, …). The
     * unique key keeps reactions idempotent and un-farmable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_post_reactions')) {
            Schema::create('forum_post_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_post_id')->constrained('forum_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reaction')->default('like');
                $table->timestamps();

                $table->unique(['forum_post_id', 'user_id', 'reaction']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_reactions');
    }
};
