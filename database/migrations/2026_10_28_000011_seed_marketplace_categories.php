<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default sections so a fresh marketplace has a usable browse page
     * before the admin category tooling ships. updateOrInsert keeps this
     * idempotent and lets admins rename categories later.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_categories')) {
            return;
        }

        $now = now();
        $defaults = [
            ['name' => 'Comics', 'slug' => 'comics', 'icon' => '📚', 'description' => 'Comics hospedados no site, avulsos ou em lote.'],
            ['name' => 'Figures & Toys', 'slug' => 'figures-toys', 'icon' => '🧸', 'description' => 'Figures, action figures, pelúcias e brinquedos.'],
            ['name' => 'Prints & Arte', 'slug' => 'prints-arte', 'icon' => '🎨', 'description' => 'Prints, pôsteres, originais e material impresso.'],
            ['name' => 'Digital', 'slug' => 'digital', 'icon' => '💾', 'description' => 'Pacotes de arte, PSDs e arquivos entregues manualmente.'],
            ['name' => 'Serviços', 'slug' => 'servicos', 'icon' => '🛠️', 'description' => 'Comissões, edições e outros trabalhos sob encomenda.'],
            ['name' => 'Outros', 'slug' => 'outros', 'icon' => '📦', 'description' => 'Tudo que não cabe nas outras categorias.'],
        ];

        foreach ($defaults as $index => $category) {
            DB::table('marketplace_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'parent_id' => null,
                    'sort_order' => $index + 1,
                    'is_visible' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_categories')) {
            return;
        }

        DB::table('marketplace_categories')->whereIn('slug', [
            'comics', 'figures-toys', 'prints-arte', 'digital', 'servicos', 'outros',
        ])->whereNull('parent_id')->delete();
    }
};
