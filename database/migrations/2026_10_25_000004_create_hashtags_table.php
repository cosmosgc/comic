<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hashtags parsed from post text + the post pivot. use_count is a
     * denormalized counter for the trending sidebar.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hashtags')) {
            Schema::create('hashtags', function (Blueprint $table) {
                $table->id();
                $table->string('tag', 80)->unique();
                $table->unsignedInteger('use_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hashtag_post')) {
            Schema::create('hashtag_post', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hashtag_id')->constrained('hashtags')->cascadeOnDelete();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['hashtag_id', 'post_id']);
                $table->index(['post_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hashtag_post');
        Schema::dropIfExists('hashtags');
    }
};
