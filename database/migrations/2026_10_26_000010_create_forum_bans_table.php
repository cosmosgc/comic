<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum bans. forum_category_id null = forum-wide.
     * banned_until null = permanent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_bans')) {
            Schema::create('forum_bans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('forum_category_id')->nullable()->constrained('forum_categories')->cascadeOnDelete();
                $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamp('banned_until')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'forum_category_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_bans');
    }
};
