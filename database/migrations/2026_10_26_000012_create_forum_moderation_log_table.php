<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for moderator actions (lock, pin, move, delete,
     * approve, ban).
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_moderation_log')) {
            Schema::create('forum_moderation_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->morphs('subject');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_moderation_log');
    }
};
