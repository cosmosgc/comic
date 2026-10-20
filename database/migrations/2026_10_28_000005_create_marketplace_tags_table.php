<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_tags')) {
            Schema::create('marketplace_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->unsignedInteger('use_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketplace_listing_tag')) {
            Schema::create('marketplace_listing_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->foreignId('marketplace_tag_id')->constrained('marketplace_tags')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['marketplace_listing_id', 'marketplace_tag_id'], 'marketplace_listing_tag_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_tag');
        Schema::dropIfExists('marketplace_tags');
    }
};
