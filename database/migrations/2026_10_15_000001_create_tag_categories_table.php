<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tag categories (e621-style: artist, character, copyright, species,
     * general, meta, lore) with per-category colors. Tags point at a
     * category; rendering falls back to the "general" style when unset.
     */
    public function up(): void
    {
        Schema::create('tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('background', 7)->default('#252525');
            $table->string('border', 7)->default('#5a5a5a');
            $table->string('text_color', 7)->default('#f0f0f0');
            // EH-style namespaces that auto-map to this category
            // (e.g. ["artist", "group"]), stored as JSON.
            $table->json('namespaces')->nullable();
            // Keyword fallback (Flask-style substring match), JSON array.
            $table->json('keywords')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('tag_categories')->nullOnDelete();
        });

        foreach ($this->defaults() as $index => $category) {
            DB::table('tag_categories')->updateOrInsert(
                ['name' => $category['name']],
                array_merge($category, [
                    'sort_order' => $index + 1,
                    'namespaces' => json_encode($category['namespaces']),
                    'keywords' => json_encode($category['keywords']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('tag_categories');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaults(): array
    {
        return [
            [
                'name' => 'artist',
                'label' => 'Artist',
                'background' => '#372414',
                'border' => '#b36f28',
                'text_color' => '#ffe3c2',
                'namespaces' => ['artist', 'group'],
                'keywords' => ['cladz'],
            ],
            [
                'name' => 'copyright',
                'label' => 'Copyright',
                'background' => '#1d2d39',
                'border' => '#4d88a8',
                'text_color' => '#d7f1ff',
                'namespaces' => ['copyright', 'parody', 'series'],
                'keywords' => ['nintendo', 'pokemon'],
            ],
            [
                'name' => 'character',
                'label' => 'Character',
                'background' => '#231f3d',
                'border' => '#655fd2',
                'text_color' => '#e3ddff',
                'namespaces' => ['character'],
                'keywords' => ['echo_(echovalia)'],
            ],
            [
                'name' => 'species',
                'label' => 'Species',
                'background' => '#203322',
                'border' => '#4d8c4f',
                'text_color' => '#dcffdf',
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
                ],
            ],
            [
                'name' => 'general',
                'label' => 'General',
                'background' => '#252525',
                'border' => '#5a5a5a',
                'text_color' => '#f0f0f0',
                'namespaces' => ['general', 'female', 'male', 'mixed', 'other', 'language'],
                'keywords' => [],
            ],
            [
                'name' => 'meta',
                'label' => 'Meta',
                'background' => '#2f2438',
                'border' => '#7a57a0',
                'text_color' => '#f0ddff',
                'namespaces' => ['meta'],
                'keywords' => ['absurd_res', 'digital_media_(artwork)', 'hi_res', 'monochrome'],
            ],
            [
                'name' => 'lore',
                'label' => 'Lore',
                'background' => '#2e2519',
                'border' => '#8f6840',
                'text_color' => '#fbe6ce',
                'namespaces' => ['lore'],
                'keywords' => [],
            ],
        ];
    }
};
