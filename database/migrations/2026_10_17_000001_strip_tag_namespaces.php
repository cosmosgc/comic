<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Strip EH namespace prefixes from stored tag names ("male:dog boy"
     * becomes "dog boy") and resolve every touched tag through the
     * namespace rules (unknown namespaces auto-create their category).
     * Same-named twins merge into one tag with comics preserved.
     */
    public function up(): void
    {
        // Resolution learns vocabulary into `learned_keywords`, so the
        // column must exist first — this migration sorts before the one
        // that originally added it.
        if (! Schema::hasColumn('tag_categories', 'learned_keywords')) {
            Schema::table('tag_categories', function (Blueprint $table) {
                $table->json('learned_keywords')->nullable()->after('keywords');
            });
        }

        $names = DB::table('tags')
            ->where('name', 'like', '%:%')
            ->orderBy('id')
            ->pluck('name');

        foreach ($names as $name) {
            Tag::findOrCreateResolved($name);
        }
    }

    public function down(): void
    {
        // Names cannot be un-stripped; nothing to revert.
    }
};
