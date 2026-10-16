<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Import inspection: per-page downloaded size plus an append-only
     * event log on the import record (shown in the progress view).
     */
    public function up(): void
    {
        if (Schema::hasTable('eh_import_pages') && ! Schema::hasColumn('eh_import_pages', 'file_size')) {
            Schema::table('eh_import_pages', function (Blueprint $table) {
                $table->unsignedBigInteger('file_size')->nullable()->after('image_url');
            });
        }

        if (Schema::hasTable('eh_imports') && ! Schema::hasColumn('eh_imports', 'log')) {
            Schema::table('eh_imports', function (Blueprint $table) {
                $table->text('log')->nullable()->after('total_pages');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('eh_import_pages', 'file_size')) {
            Schema::table('eh_import_pages', function (Blueprint $table) {
                $table->dropColumn('file_size');
            });
        }
        if (Schema::hasColumn('eh_imports', 'log')) {
            Schema::table('eh_imports', function (Blueprint $table) {
                $table->dropColumn('log');
            });
        }
    }
};
