<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic uploads hanging off a thread (OP) or a single post.
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_attachments')) {
            Schema::create('forum_attachments', function (Blueprint $table) {
                $table->id();
                $table->morphs('attachable');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedInteger('image_width')->nullable();
                $table->unsignedInteger('image_height')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_attachments');
    }
};
