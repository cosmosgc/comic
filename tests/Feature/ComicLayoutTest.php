<?php

namespace Tests\Feature;

use App\Models\Comic;
use Tests\TestCase;

class ComicLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_home_defaults_to_grid_layout(): void
    {
        Comic::factory()->create(['title' => 'Grid Comic '.uniqid()]);

        $this->get('/')->assertOk()
            ->assertSee('data-comics-layout-root="grid"', false)
            ->assertDontSee('data-comics-layout-root="list"', false);
    }

    public function test_list_cookie_renders_horizontal_rows(): void
    {
        Comic::factory()->create(['title' => 'List Comic '.uniqid()]);

        // Unencrypted, like the real JS-set cookie (see bootstrap/app.php).
        $this->withUnencryptedCookie('comics_layout', 'list')->get('/')->assertOk()
            ->assertSee('data-comics-layout-root="list"', false)
            ->assertSee('data-comic-list', false)
            ->assertDontSee('data-comics-layout-root="grid"', false);
    }

    public function test_unknown_layout_cookie_falls_back_to_grid(): void
    {
        $this->withUnencryptedCookie('comics_layout', 'masonry')->get('/')->assertOk()
            ->assertSee('data-comics-layout-root="grid"', false);
    }

    public function test_top_comics_sit_in_sidebar_below_popular_tags(): void
    {
        Comic::factory()->create(['title' => 'Top Sidebar Comic '.uniqid(), 'view_count' => 99]);

        $content = $this->get('/')->assertOk()->getContent();

        // Anchor on the sidebar comment: <head> metas also mention the
        // section title, so plain strpos would hit those first.
        $tagsPos = strpos($content, 'Tags populares');
        $topPos = strpos($content, 'Top comics (only pages');
        $this->assertNotFalse($tagsPos);
        $this->assertNotFalse($topPos);
        $this->assertLessThan($topPos, $tagsPos);
    }
}
