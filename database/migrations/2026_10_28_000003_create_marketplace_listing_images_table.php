<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_listing_images')) {
            Schema::create('marketplace_listing_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime', 100)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->index(['marketplace_listing_id', 'position'], 'marketplace_images_listing_position_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_images');
    }
};
