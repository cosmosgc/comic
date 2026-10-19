<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Explicit edit stamp: `updated_at` also changes on
            // increment('view_count'), so it cannot drive the badge.
            $table->timestamp('edited_at')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
