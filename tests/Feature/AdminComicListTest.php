<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\ComicTransfer;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use App\Support\ComicDiskUsage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminComicListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        $this->artisan('migrate');
        ComicDiskUsage::flush();
    }

    public function test_list_shows_cover_thumbnail_and_placeholder(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Com capa',
            'image_path' => 'comics/1/cover.jpg',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Sem capa',
            'image_path' => null,
        ]);

        $this->actingAs($admin)->get('/admin/comics')
            ->assertOk()
            ->assertSee('storage/comics/1/cover.jpg')
            ->assertSee('Capa de Com capa')
            ->assertSee('data-cover-placeholder', false)
            ->assertSee('data-comics-filter-all', false)
            ->assertSee('data-comics-count', false)
            ->assertSee('data-comic-delete', false);
    }

    public function test_list_shows_page_count_and_size_columns(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Com páginas']);
        $empty = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Sem páginas']);

        foreach ([1, 2, 3] as $number) {
            Page::create([
                'comic_id' => $comic->id,
                'image_path' => "comics/{$comic->id}/pages/manual-page-{$number}.bin",
                'page_number' => $number,
            ]);
        }

        // 1.5 MB of page files on disk.
        $pagesDir = public_path("storage/comics/{$comic->id}/pages");
        File::ensureDirectoryExists($pagesDir);
        File::put("{$pagesDir}/manual-page-1.bin", str_repeat('x', 786432));
        File::put("{$pagesDir}/manual-page-2.bin", str_repeat('x', 786432));

        try {
            $html = $this->actingAs($admin)->get(route('admin.comics'))
                ->assertOk()
                ->assertSee('<th class="w-[5%]">Páginas</th>', false)
                ->assertSee('<th class="w-[7%]">Tamanho (MB)</th>', false)
                ->getContent();

            // Comic with pages: count 3 and 1,5 MB, both sortable via the
            // raw data-order numbers (bytes for the size column).
            $this->assertMatchesRegularExpression('/data-order="3">\s*3\s*<\/td>/', $html);
            $this->assertStringContainsString('data-order="1572864"', $html);
            $this->assertMatchesRegularExpression('/>\s*1,5\s*<\/td>/', $html);

            // Comic without pages or files: 0 and 0,0.
            $this->assertStringContainsString('data-order="0" title="0 bytes">', $html);
            $this->assertMatchesRegularExpression('/data-order="0">\s*0\s*<\/td>/', $html);
        } finally {
            File::deleteDirectory(public_path("storage/comics/{$comic->id}"));
            ComicDiskUsage::flush();
        }

        $this->assertDatabaseHas('pages', ['comic_id' => $comic->id]);
        $this->assertDatabaseHas('comics', ['id' => $empty->id]);
    }

    public function test_admin_can_filter_comics_without_tags(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Comic sem tags',
        ]);
        $tagged = Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Comic com tags',
        ]);
        $tagged->tags()->attach(Tag::create(['name' => 'tagged'])->id);

        $response = $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'untagged']))
            ->assertOk()
            // Every row is rendered; the filter runs in the browser.
            ->assertSee('Comic sem tags')
            ->assertSee('Comic com tags')
            ->assertSee('data-comics-filter="untagged"', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-comics-filter="untagged"[^>]*aria-pressed="true"/s', $html);
        $this->assertSame(1, substr_count($html, 'data-untagged="1"'));
        $this->assertSame(1, substr_count($html, 'data-untagged="0"'));
    }

    public function test_admin_can_filter_comics_scheduled_for_the_future(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Ainda vai lançar',
            'published_at' => now()->addWeek(),
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Já publicado',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.comics', ['filters' => ['scheduled']]))
            ->assertOk()
            ->assertSee('Ainda vai lançar')
            ->assertSee('Já publicado')
            ->assertSee('data-comics-filter="scheduled"', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-comics-filter="scheduled"[^>]*aria-pressed="true"/s', $html);
        $this->assertSame(1, substr_count($html, 'data-scheduled="1"'));
        $this->assertSame(1, substr_count($html, 'data-scheduled="0"'));
    }

    public function test_admin_can_filter_potential_duplicate_comics(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'O Último Desejo do Lobo',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => '[Zourik] O Ultimo DESEJO do Lobo (Zootopia) [Português-BR]',
        ]);
        Comic::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Uma história completamente diferente',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'duplicates']))
            ->assertOk()
            ->assertSee('O Último Desejo do Lobo')
            ->assertSee('[Zourik] O Ultimo DESEJO do Lobo')
            // Still rendered so the filter can be toggled off without a reload.
            ->assertSee('Uma história completamente diferente')
            ->assertSee('data-comics-filter="duplicates"', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-comics-filter="duplicates"[^>]*aria-pressed="true"/s', $html);
        $this->assertSame(2, substr_count($html, 'data-duplicate="1"'));
        $this->assertSame(1, substr_count($html, 'data-duplicate="0"'));
    }

    public function test_admin_cannot_use_an_unknown_comic_attention_filter(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'reported']))
            ->assertSessionHasErrors('attention');

        $this->actingAs($admin)->get(route('admin.comics', ['filters' => ['reported']]))
            ->assertSessionHasErrors('filters.0');
    }

    public function test_attention_filters_can_be_combined(): void
    {
        config(['transfer.remote_url' => 'https://remote.test', 'transfer.remote_token' => 'secret']);

        $admin = User::factory()->create(['admin_level' => 1]);
        Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Alfa']);
        $done = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Beta']);
        $tagged = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Gama']);
        $tagged->tags()->attach(Tag::create(['name' => 'tagged'])->id);

        ComicTransfer::create([
            'comic_id' => $done->id,
            'remote_url' => 'https://remote.test',
            'status' => ComicTransfer::STATUS_DONE,
            'next_page' => 1,
            'total_pages' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.comics', ['filters' => ['untagged', 'remote']]))
            ->assertOk();

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-comics-filter="untagged"[^>]*aria-pressed="true"/s', $html);
        $this->assertMatchesRegularExpression('/data-comics-filter="remote"[^>]*aria-pressed="true"/s', $html);
        $this->assertStringContainsString('["untagged","remote"]', $html);
    }

    public function test_admin_can_filter_comics_that_are_not_on_the_remote(): void
    {
        config(['transfer.remote_url' => 'https://remote.test', 'transfer.remote_token' => 'secret']);

        $admin = User::factory()->create(['admin_level' => 1]);
        $done = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Já no remoto']);
        $failed = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Envio falhou']);
        Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Nunca enviado']);

        ComicTransfer::create([
            'comic_id' => $done->id,
            'remote_url' => 'https://remote.test',
            'status' => ComicTransfer::STATUS_DONE,
            'next_page' => 2,
            'total_pages' => 2,
        ]);
        ComicTransfer::create([
            'comic_id' => $failed->id,
            'remote_url' => 'https://remote.test',
            'status' => ComicTransfer::STATUS_FAILED,
            'next_page' => 1,
            'total_pages' => 2,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.comics', ['attention' => 'remote']))
            ->assertOk()
            ->assertSee('Já no remoto')
            ->assertSee('Envio falhou')
            ->assertSee('Nunca enviado')
            ->assertSee('data-comics-filter="remote"', false);

        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'data-remote="1"'));
        $this->assertSame(1, substr_count($html, 'data-remote="0"'));
    }

    public function test_delete_returns_json_for_in_place_removal(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Comic para excluir']);

        $this->actingAs($admin)
            ->deleteJson(route('admin.comics.destroy', $comic))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('id', $comic->id)
            ->assertJsonPath('message', 'Comic "Comic para excluir" excluído com todos os arquivos.');

        $this->assertDatabaseMissing('comics', ['id' => $comic->id]);
    }

    public function test_delete_redirect_keeps_the_active_filters_without_javascript(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);
        $comic = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Comic para excluir']);

        $this->actingAs($admin)
            ->delete(route('admin.comics.destroy', $comic), ['filters' => ['untagged', 'remote']])
            ->assertRedirect(route('admin.comics', ['filters' => ['untagged', 'remote']]));

        $this->assertDatabaseMissing('comics', ['id' => $comic->id]);
    }

    public function test_batch_transfer_controls_only_appear_when_a_remote_is_configured(): void
    {
        config(['transfer.remote_url' => 'https://remote.test', 'transfer.remote_token' => 'secret']);

        $admin = User::factory()->create(['admin_level' => 1]);
        $done = Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Já enviado']);
        Comic::factory()->create(['user_id' => $admin->id, 'title' => 'Ainda local']);
        ComicTransfer::create([
            'comic_id' => $done->id,
            'remote_url' => 'https://remote.test',
            'status' => ComicTransfer::STATUS_DONE,
            'next_page' => 1,
            'total_pages' => 1,
        ]);

        $this->actingAs($admin)->get('/admin/comics')
            ->assertOk()
            ->assertSee('Enviar selecionados p/ remoto')
            ->assertSee('data-comics-select-all', false)
            ->assertSee('Selecionar todos os listados');

        // Only the comic still missing on the remote can be selected.
        $this->actingAs($admin)->get('/admin/comics')
            ->assertSee('aria-label="Selecionar Ainda local"', false)
            ->assertDontSee('aria-label="Selecionar Já enviado"', false);

        config(['transfer.remote_url' => '', 'transfer.remote_token' => '']);

        $this->actingAs($admin)->get('/admin/comics')
            ->assertOk()
            ->assertDontSee('admin-btn-success admin-btn-sm" data-comics-batch', false)
            ->assertDontSee('data-comics-filter="remote"', false);
    }
}
