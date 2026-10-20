<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provider identities linked to a local account. Tokens are deliberately
     * not stored: the OAuth flow only needs the public profile at login.
     */
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 20);
                $table->string('provider_user_id', 190);
                $table->string('nickname', 190)->nullable();
                $table->string('name', 190)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('avatar_url', 500)->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_user_id']);
                $table->unique(['user_id', 'provider']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
