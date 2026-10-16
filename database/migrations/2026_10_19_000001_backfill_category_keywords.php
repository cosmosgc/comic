<?php

use App\Models\Tag;
use App\Services\TagCategorizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teach every non-general category its current members' names
     * (what the write path now learns on import). Keeps future
     * recategorizations stable: "dog boy" in Male re-resolves to Male
     * instead of flipping to Species via the generic keywords.
     */
    public function up(): void
    {
        // learn() writes `learned_keywords`: ensure the column even when
        // this migration runs before the one that originally added it
        // (e.g. hosts where the strip migration already ran earlier).
        if (! Schema::hasColumn('tag_categories', 'learned_keywords')) {
            Schema::table('tag_categories', function (Blueprint $table) {
                $table->json('learned_keywords')->nullable()->after('keywords');
            });
        }

        $categorizer = app(TagCategorizer::class);

        Tag::whereNotNull('category_id')
            ->orderBy('id')
            ->chunk(500, function ($tags) use ($categorizer) {
                foreach ($tags as $tag) {
                    $category = $tag->category;
                    if ($category === null || $category->name === 'general') {
                        continue;
                    }
                    $categorizer->learn($category, $tag->name);
                }
            });
    }

    public function down(): void
    {
        // Learned keywords are data; nothing to revert.
    }
};
