<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Key/value overrides for config/marketplace.php, same pattern as
     * gamification_settings / forum_settings.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_settings')) {
            Schema::create('marketplace_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings');
    }
};
