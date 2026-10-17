<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Key/value forum tuning edited in Admin → Fórum, overlaid onto
     * config/forum.php at boot (mirrors gamification_settings).
     */
    public function up(): void
    {
        if (! Schema::hasTable('forum_settings')) {
            Schema::create('forum_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_settings');
    }
};
