<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * EH-style rules, round 2: furry/dog/cat kinds → Species; sole
     * male/female, translated, full color, comic → Meta. Bare attribute
     * namespaces (female:/male:/other:/language:) no longer pin tags to
     * General so the keyword rules can fire ("female:furry" → Species,
     * "other:full color" → Meta); unmatched tags still fall back to
     * General. Overwrites the shipped defaults — re-apply any admin
     * panel tweaks to these categories afterwards, then run the
     * "Recategorizar todas" action.
     */
    public function up(): void
    {
        $rules = [
            'species' => [
                'namespaces' => ['species'],
                'keywords' => [
                    'cobalion',
                    'eeveelution',
                    'elemental_creature',
                    'flora_fauna',
                    'generation_4_pokemon',
                    'generation_5_pokemon',
                    'leafeon',
                    'legendary_pokemon',
                    'pokemon_(species)',
                    'furry',
                    'dog boy',
                    'dog girl',
                    'cat boy',
                    'cat girl',
                ],
            ],
            'general' => [
                'namespaces' => ['general'],
                'keywords' => [],
            ],
            'meta' => [
                'namespaces' => ['meta'],
                'keywords' => [
                    'absurd_res',
                    'digital_media_(artwork)',
                    'hi_res',
                    'monochrome',
                    'sole male',
                    'sole female',
                    'translated',
                    'full color',
                    'full colour',
                    'comic',
                ],
            ],
        ];

        foreach ($rules as $name => $rule) {
            DB::table('tag_categories')
                ->where('name', $name)
                ->update([
                    'namespaces' => json_encode($rule['namespaces']),
                    'keywords' => json_encode($rule['keywords']),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('tag_categories')
            ->where('name', 'general')
            ->update([
                'namespaces' => json_encode(['general', 'female', 'male', 'mixed', 'other', 'language']),
                'updated_at' => now(),
            ]);
    }
};
