<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members-only comics: the flag keeps a comic out of guests' reach
     * (reader answers 404, lists show a locked teaser card) without
     * hiding it from the catalogue entirely.
     * Idempotent for hosts applying migrations in any order.
     */
    public function up(): void
    {
        if (Schema::hasColumn('comics', 'is_exclusive')) {
            return;
        }

        Schema::table('comics', function (Blueprint $table) {
            $table->boolean('is_exclusive')->default(false)->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comics', function (Blueprint $table) {
            $table->dropColumn('is_exclusive');
        });
    }
};
