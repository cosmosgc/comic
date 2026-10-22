<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Galleries picked from the E-Hentai search results, waiting to be
     * turned into scheduled comics and downloaded one after another.
     * The comic link lets a paused queue resume on its current gallery.
     */
    public function up(): void
    {
        if (! Schema::hasTable('eh_import_queue')) {
            Schema::create('eh_import_queue', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('gid');
                $table->string('token', 32);
                $table->string('title', 255);
                $table->string('thumb', 500)->nullable();
                $table->string('source_url', 500);
                $table->string('status', 20)->default('pending');
                $table->foreignId('comic_id')->nullable()->constrained('comics')->nullOnDelete();
                $table->string('error', 500)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique('gid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eh_import_queue');
    }
};
