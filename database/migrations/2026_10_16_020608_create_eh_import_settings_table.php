<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Import tuning edited in the admin view instead of .env
     * (key → value, overlaid onto config/ehimport.php at boot).
     */
    public function up(): void
    {
        if (! Schema::hasTable('eh_import_settings')) {
            Schema::create('eh_import_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eh_import_settings');
    }
};
