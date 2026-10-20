<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banner shown as the board hero on the category page.
     * Managed by admins in Admin → Fórum → Categorias.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('forum_categories', 'banner_image_path')) {
            Schema::table('forum_categories', function (Blueprint $table) {
                $table->string('banner_image_path')->nullable()->after('color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_categories', 'banner_image_path')) {
            Schema::table('forum_categories', function (Blueprint $table) {
                $table->dropColumn('banner_image_path');
            });
        }
    }
};
