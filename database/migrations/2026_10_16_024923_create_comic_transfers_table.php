<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-to-server transfer state (local side): which remote comic
     * is being built and which page goes next, so polling survives
     * restarts and long galleries.
     */
    public function up(): void
    {
        if (! Schema::hasTable('comic_transfers')) {
            Schema::create('comic_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comic_id')->unique()->constrained('comics')->onDelete('cascade');
                $table->string('remote_url', 500);
                $table->string('status', 20)->default('sending');
                $table->unsignedBigInteger('remote_comic_id')->nullable();
                $table->unsignedInteger('next_page')->default(1);
                $table->unsignedInteger('total_pages')->default(0);
                $table->string('error', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comic_transfers');
    }
};
