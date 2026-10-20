<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\User;
use App\Services\ChangelogReader;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_index_lists_entries(): void
    {
        // The first page shows the most recent entries, so assert against
        // whatever the newest one is instead of a fixed old title.
        $newest = ChangelogReader::fromDefaultPath()->all()->first();

        $response = $this->get('/changelog');

        $response->assertStatus(200);
        $response->assertSee('Novidades', false);
        $response->assertSee($newest['title']);
    }

    public function test_index_search_filters_entries(): void
    {
        $response = $this->get('/changelog?q=413');

        $response->assertStatus(200);
        $response->assertSee('Sequential comic uploads');
        $response->assertDontSee('Admin Migrations panel');
    }

    public function test_index_links_guests_to_calendar_for_an_unavailable_scheduled_comic(): void
    {
        Comic::factory()->create([
            'title' => 'Próximo Exclusivo XYZ',
            'published_at' => now()->addDays(3),
        ]);

        $this->get('/changelog')
            ->assertSee('data-upcoming-comic', false)
            ->assertSee('data-upcoming-link', false)
            ->assertSee('href="'.route('comics.calendar').'"', false)
            ->assertSee('Ver calendário')
            ->assertDontSee('Próximo Exclusivo XYZ');
    }

    public function test_index_links_members_to_an_available_scheduled_comic(): void
    {
        $comic = Comic::factory()->create([
            'title' => 'Pré-estreia XYZ',
            'published_at' => now()->addDays(3),
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/changelog')
            ->assertSee('data-upcoming-link', false)
            ->assertSee('href="'.route('comics.showBySlug', $comic->slug).'"', false)
            ->assertSee('Pré-estreia XYZ')
            ->assertSee('Ler agora');
    }

    public function test_show_renders_single_entry(): void
    {
        $response = $this->get('/changelog/2026-10-04-admin-migrations-panel');

        $response->assertStatus(200);
        $response->assertSee('Admin Migrations panel');
        $response->assertSee('Novidades relacionadas');
    }

    public function test_show_unknown_entry_404s(): void
    {
        $response = $this->get('/changelog/no-such-entry');

        $response->assertStatus(404);
    }
}
