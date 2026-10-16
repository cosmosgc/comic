<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hidden categories keep their tags stored (search and the editor
     * still see them) but render nothing on public tag lists.
     */
    public function up(): void
    {
        Schema::table('tag_categories', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('tag_categories', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
