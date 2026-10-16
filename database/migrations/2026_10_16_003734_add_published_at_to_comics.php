<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled publishing: null/past means publicly visible, a future
     * timestamp hides the comic until then (owner/admin preview only).
     * Idempotent for hosts applying migrations in any order.
     */
    public function up(): void
    {
        if (Schema::hasColumn('comics', 'published_at')) {
            return;
        }

        Schema::table('comics', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
