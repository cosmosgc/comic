<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gamification tuning edited in the admin panel (key → JSON value,
     * overlaid onto config/gamification.php at boot). Deleting a row
     * restores the file default.
     */
    public function up(): void
    {
        if (! Schema::hasTable('gamification_settings')) {
            Schema::create('gamification_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_settings');
    }
};
