<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Directed follow graph: follower_id follows followed_id.
     * Self-follows are prevented in code, not here.
     */
    public function up(): void
    {
        if (! Schema::hasTable('follows')) {
            Schema::create('follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['follower_id', 'followed_id']);
                $table->index(['followed_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
