<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Frontend assets are built by Vite and self-hosted (issues.md #16):
 * every library that used to come from a CDN is bundled now, so no page
 * may pull a script or stylesheet from another origin.
 */
class AssetBundlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_public_layout_loads_bundled_assets_only(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/build/assets/app-', $html);
        $this->assertNoExternalAssets($html);

        // The sidebar tag icon is an inline SVG since FontAwesome is gone.
        $this->assertStringNotContainsString('font-awesome', $html);
    }

    public function test_admin_layout_loads_the_admin_bundle_only(): void
    {
        $admin = User::factory()->create(['admin_level' => 1]);

        $html = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('/build/assets/admin-', $html);
        $this->assertNoExternalAssets($html);
    }

    /**
     * Guard against a CDN tag sneaking back in: every script/stylesheet
     * URL must be relative or on this application's own host.
     */
    private function assertNoExternalAssets(string $html): void
    {
        preg_match_all('/<script[^>]*>/i', $html, $scripts);
        foreach ($scripts[0] as $script) {
            if (preg_match('/src=(["\'])(.*?)\1/i', $script, $matches)) {
                $this->assertLocalUrl($matches[2], "script tag: {$script}");
            }
        }

        preg_match_all('/<link[^>]*>/i', $html, $links);
        foreach ($links[0] as $link) {
            if (stripos($link, 'stylesheet') === false) {
                continue;
            }
            if (preg_match('/href=(["\'])(.*?)\1/i', $link, $matches)) {
                $this->assertLocalUrl($matches[2], "stylesheet tag: {$link}");
            }
        }
    }

    private function assertLocalUrl(string $url, string $context): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null) {
            return; // Relative URL: served by this application.
        }

        $this->assertSame(
            request()->getHost(),
            $host,
            "Asset loaded from another origin — {$context}"
        );
    }
}
