<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badge keys the user chose to feature on their profile card
     * (empty/null = show every earned badge).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'showcase_badges')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('showcase_badges')->nullable()->after('longest_streak');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'showcase_badges')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('showcase_badges');
            });
        }
    }
};
