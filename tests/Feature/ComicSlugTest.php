<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Slug rules for comic creation (issues.md #7): a client-supplied slug
 * is sanitized and de-duplicated instead of being trusted verbatim.
 */
class ComicSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Slug Test '.uniqid(),
            'images' => [UploadedFile::fake()->image('page01.jpg', 30, 30)],
        ], $overrides);
    }

    protected function cleanup(Comic $comic): void
    {
        foreach ($comic->pages()->pluck('image_path') as $path) {
            @unlink(public_path('storage/'.$path));
        }
        if ($comic->image_path) {
            @unlink(public_path('storage/'.$comic->image_path));
        }
        @rmdir(public_path("storage/comics/{$comic->id}"));
    }

    public function test_duplicate_slug_is_suffixed_instead_of_failing(): void
    {
        $user = User::factory()->create();
        Comic::factory()->create(['slug' => 'shared-slug']);

        $response = $this->actingAs($user)->postJson('/comics', $this->payload([
            'title' => 'Outro comic',
            'slug' => 'shared-slug',
        ]));

        $response->assertOk();
        $comic = Comic::findOrFail((int) $response->json('comic_id'));
        $this->assertSame('shared-slug-1', $comic->slug);

        $this->cleanup($comic);
    }

    public function test_client_slug_is_sanitized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/comics', $this->payload([
            'title' => 'Ignorado',
            'slug' => 'Meu Comic Bom!!!',
        ]));

        $response->assertOk();
        $comic = Comic::findOrFail((int) $response->json('comic_id'));
        $this->assertSame('meu-comic-bom', $comic->slug);

        $this->cleanup($comic);
    }

    public function test_blank_slug_falls_back_to_the_title(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/comics', $this->payload([
            'title' => 'Título Legal',
            'slug' => '   ',
        ]));

        $response->assertOk();
        $comic = Comic::findOrFail((int) $response->json('comic_id'));
        $this->assertSame('titulo-legal', $comic->slug);

        $this->cleanup($comic);
    }

    public function test_title_without_usable_characters_gets_a_fallback_slug(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/comics', $this->payload([
            'title' => '🎉🎉',
        ]));

        $response->assertOk();
        $comic = Comic::findOrFail((int) $response->json('comic_id'));
        $this->assertNotSame('', $comic->slug);
        $this->assertSame('comic', $comic->slug);

        $this->cleanup($comic);
    }

    public function test_slug_longer_than_the_column_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/comics', $this->payload(['slug' => str_repeat('a', 300)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_backfill_command_fills_missing_slugs(): void
    {
        // Legacy rows have no slug at all — the old public endpoint that
        // fixed them is gone, so the console command must.
        $legacy = Comic::factory()->create(['title' => 'Título Antigo', 'slug' => '']);
        Comic::factory()->create(['title' => 'Outro', 'slug' => 'titulo-antigo']);

        $this->artisan('comics:backfill-slugs')->assertSuccessful();

        // The base slug is taken, so the command must suffix it.
        $this->assertSame('titulo-antigo-1', $legacy->fresh()->slug);
    }

    public function test_backfill_command_dry_run_changes_nothing(): void
    {
        $legacy = Comic::factory()->create(['title' => 'Sem Slug', 'slug' => '']);

        $this->artisan('comics:backfill-slugs --dry-run')->assertSuccessful();

        $this->assertSame('', $legacy->fresh()->slug);
    }

    public function test_backfill_command_reports_when_nothing_is_missing(): void
    {
        Comic::factory()->create(['slug' => 'ja-tem-slug']);

        $this->artisan('comics:backfill-slugs')
            ->expectsOutput('Every comic already has a slug.')
            ->assertSuccessful();
    }
}
