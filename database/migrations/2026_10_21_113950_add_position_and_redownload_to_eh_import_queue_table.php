<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual order for the bulk import queue (drag and drop) and the
     * "download every page again" flag for comics already on the site.
     */
    public function up(): void
    {
        Schema::table('eh_import_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('eh_import_queue', 'position')) {
                $table->unsignedInteger('position')->default(0)->after('id');
            }
            if (! Schema::hasColumn('eh_import_queue', 'redownload')) {
                $table->boolean('redownload')->default(false)->after('status');
            }
        });

        // Existing rows keep their current order (id) until someone drags.
        DB::table('eh_import_queue')
            ->where('position', 0)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id, int $index): void {
                DB::table('eh_import_queue')->where('id', $id)->update(['position' => $index + 1]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eh_import_queue', function (Blueprint $table) {
            if (Schema::hasColumn('eh_import_queue', 'position')) {
                $table->dropColumn('position');
            }
            if (Schema::hasColumn('eh_import_queue', 'redownload')) {
                $table->dropColumn('redownload');
            }
        });
    }
};
