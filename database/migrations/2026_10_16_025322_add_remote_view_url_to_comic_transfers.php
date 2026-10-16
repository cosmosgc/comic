<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the remote view URL at finalize time so the admin list
     * can link straight to the transferred comic.
     */
    public function up(): void
    {
        if (Schema::hasTable('comic_transfers') && ! Schema::hasColumn('comic_transfers', 'remote_view_url')) {
            Schema::table('comic_transfers', function (Blueprint $table) {
                $table->string('remote_view_url', 500)->nullable()->after('remote_comic_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('comic_transfers', 'remote_view_url')) {
            Schema::table('comic_transfers', function (Blueprint $table) {
                $table->dropColumn('remote_view_url');
            });
        }
    }
};
