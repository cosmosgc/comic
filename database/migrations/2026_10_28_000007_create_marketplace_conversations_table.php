<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One contact thread per (listing, buyer). Seller is denormalized so
     * the inbox can filter without joining the listing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_conversations')) {
            Schema::create('marketplace_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->string('subject')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('buyer_unread_count')->default(0);
                $table->unsignedInteger('seller_unread_count')->default(0);
                $table->string('status', 20)->default('open');
                $table->timestamps();

                $table->unique(['marketplace_listing_id', 'buyer_id'], 'marketplace_conversations_unique');
                $table->index(['seller_id', 'last_message_at']);
                $table->index(['buyer_id', 'last_message_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_conversations');
    }
};
