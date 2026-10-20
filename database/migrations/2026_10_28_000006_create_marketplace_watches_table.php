<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buyer watchlist ("favoritos").
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_watches')) {
            Schema::create('marketplace_watches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('marketplace_listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'marketplace_listing_id'], 'marketplace_watches_unique');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_watches');
    }
};
