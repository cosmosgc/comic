<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Topic banner shown behind the thread header.
     *
     * Premium (planned): custom topic banners will be a paid feature.
     * For now every thread owner can upload one.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('forum_threads', 'banner_image_path')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->string('banner_image_path')->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_threads', 'banner_image_path')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->dropColumn('banner_image_path');
            });
        }
    }
};
