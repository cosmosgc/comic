<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable import tracking (replaces the ephemeral cache queue):
     * one record per comic plus one row per gallery page, so retries
     * survive restarts, deploys and cache expiry.
     */
    public function up(): void
    {
        if (! Schema::hasTable('eh_imports')) {
            Schema::create('eh_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comic_id')->unique()->constrained('comics')->onDelete('cascade');
                $table->string('source_url', 500)->nullable();
                $table->unsignedBigInteger('gid')->nullable();
                $table->string('token', 32)->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('total_pages')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eh_import_pages')) {
            Schema::create('eh_import_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('eh_import_id')->constrained('eh_imports')->onDelete('cascade');
                $table->unsignedInteger('page_number');
                $table->string('page_url', 500);
                $table->string('image_url', 1000)->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->string('error', 500)->nullable();
                $table->timestamps();

                $table->unique(['eh_import_id', 'page_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eh_import_pages');
        Schema::dropIfExists('eh_imports');
    }
};
