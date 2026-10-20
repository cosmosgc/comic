<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges a listing to comics hosted on this site (the "comic" kind,
     * or a bonus item in any other kind).
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_listing_comics')) {
            Schema::create('marketplace_listing_comics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->foreignId('comic_id')->constrained('comics')->cascadeOnDelete();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['marketplace_listing_id', 'comic_id'], 'marketplace_listing_comics_unique');
                $table->index('comic_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_comics');
    }
};
