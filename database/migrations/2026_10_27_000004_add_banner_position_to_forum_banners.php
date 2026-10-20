<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Focal point (0–100%) used as CSS object-position when cropping the
     * topic and board banners. 50/50 is the centered default.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('forum_threads', 'banner_position_x')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->unsignedTinyInteger('banner_position_x')->default(50)->after('banner_image_path');
                $table->unsignedTinyInteger('banner_position_y')->default(50)->after('banner_position_x');
            });
        }

        if (! Schema::hasColumn('forum_categories', 'banner_position_x')) {
            Schema::table('forum_categories', function (Blueprint $table) {
                $table->unsignedTinyInteger('banner_position_x')->default(50)->after('banner_image_path');
                $table->unsignedTinyInteger('banner_position_y')->default(50)->after('banner_position_x');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_threads', 'banner_position_x')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->dropColumn(['banner_position_x', 'banner_position_y']);
            });
        }

        if (Schema::hasColumn('forum_categories', 'banner_position_x')) {
            Schema::table('forum_categories', function (Blueprint $table) {
                $table->dropColumn(['banner_position_x', 'banner_position_y']);
            });
        }
    }
};
