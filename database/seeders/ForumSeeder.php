<?php

namespace Database\Seeders;

use App\Models\ForumCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default forum structure. Idempotent: safe to re-run, it only creates
 * rows that do not exist yet. Run explicitly with:
 *   php artisan db:seed --class=ForumSeeder
 */
class ForumSeeder extends Seeder
{
    public function run(): void
    {
        $structure = [
            [
                'name' => 'Comunidade',
                'icon' => '💬',
                'description' => 'Conversas gerais sobre comics, autores e leitura.',
                'boards' => [
                    [
                        'name' => 'Geral',
                        'icon' => '🗨️',
                        'description' => 'Assunto livre entre membros.',
                    ],
                    [
                        'name' => 'Apresentações',
                        'icon' => '👋',
                        'description' => 'Apresente-se para a comunidade.',
                    ],
                ],
            ],
            [
                'name' => 'Ajuda',
                'icon' => '🛠️',
                'description' => 'Dúvidas, suporte e sugestões.',
                'boards' => [
                    [
                        'name' => 'Dúvidas',
                        'icon' => '❓',
                        'description' => 'Perguntas sobre o site e a leitura.',
                    ],
                    [
                        'name' => 'Bugs e sugestões',
                        'icon' => '🐞',
                        'description' => 'Reporte problemas ou proponha melhorias.',
                    ],
                ],
            ],
        ];

        foreach ($structure as $sectionOrder => $sectionData) {
            $section = ForumCategory::firstOrCreate(
                ['slug' => Str::slug($sectionData['name'])],
                [
                    'name' => $sectionData['name'],
                    'icon' => $sectionData['icon'] ?? null,
                    'description' => $sectionData['description'] ?? null,
                    'sort_order' => $sectionOrder,
                    'is_visible' => true,
                ]
            );

            foreach ($sectionData['boards'] as $boardOrder => $boardData) {
                ForumCategory::firstOrCreate(
                    ['slug' => Str::slug($boardData['name'])],
                    [
                        'parent_id' => $section->id,
                        'name' => $boardData['name'],
                        'icon' => $boardData['icon'] ?? null,
                        'description' => $boardData['description'] ?? null,
                        'sort_order' => $boardOrder,
                        'is_visible' => true,
                    ]
                );
            }
        }
    }
}
