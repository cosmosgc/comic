<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forum_polls')) {
            Schema::create('forum_polls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_thread_id')->unique()->constrained('forum_threads')->cascadeOnDelete();
                $table->string('question');
                $table->boolean('multiple')->default(false);
                $table->timestamp('closes_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('forum_poll_options')) {
            Schema::create('forum_poll_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_poll_id')->constrained('forum_polls')->cascadeOnDelete();
                $table->string('label');
                $table->unsignedInteger('position')->default(0);
                $table->unsignedInteger('votes_count')->default(0);
                $table->timestamps();

                $table->index(['forum_poll_id', 'position']);
            });
        }

        if (! Schema::hasTable('forum_poll_votes')) {
            Schema::create('forum_poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forum_poll_id')->constrained('forum_polls')->cascadeOnDelete();
                $table->foreignId('forum_poll_option_id')->constrained('forum_poll_options')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                // One vote per option per member; single-choice polls are
                // enforced in the controller by clearing the previous vote.
                $table->unique(['forum_poll_option_id', 'user_id']);
                $table->index(['forum_poll_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_poll_votes');
        Schema::dropIfExists('forum_poll_options');
        Schema::dropIfExists('forum_polls');
    }
};
