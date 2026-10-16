<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Learned vocabulary (absorbed from namespaced imports) lives apart
     * from the curated keywords so it can outrank them — and be cleared
     * per category from the admin panel.
     */
    public function up(): void
    {
        // The strip migration (which sorts earlier) already ensures this
        // column; stay idempotent for hosts in either order.
        if (Schema::hasColumn('tag_categories', 'learned_keywords')) {
            return;
        }

        Schema::table('tag_categories', function (Blueprint $table) {
            $table->json('learned_keywords')->nullable()->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('tag_categories', function (Blueprint $table) {
            $table->dropColumn('learned_keywords');
        });
    }
};
