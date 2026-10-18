<?php

namespace Tests\Feature;

use App\Models\Comic;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The public lists (home, search) follow the release date - published_at
 * when the comic was scheduled, upload time otherwise - so a comic
 * scheduled weeks ahead jumps to the top on release day instead of
 * staying buried under whatever was uploaded after it.
 */
class ComicListingOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolated scratch database: never touches the dev MySQL.
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        $this->artisan('migrate');
    }

    public function test_home_list_shows_the_newest_release_first(): void
    {
        // Released an hour ago, though it was uploaded a month ago.
        $scheduledThenReleased = $this->uploadedComic('Released Today XYZ', now()->subHour(), now()->subDays(30));
        // Uploaded yesterday and published on the spot.
        $uploadedYesterday = $this->uploadedComic('Uploaded Yesterday XYZ', null, now()->subDay());
        // Older than both, by either clock.
        $older = $this->uploadedComic('Older Release XYZ', null, now()->subDays(60));

        // Asserted on the list cards: the panels above the list use plain
        // links, so only the grid carries this marker.
        $this->get('/')->assertOk()->assertSeeInOrder([
            'data-comic-card="'.$scheduledThenReleased->id.'"',
            'data-comic-card="'.$uploadedYesterday->id.'"',
            'data-comic-card="'.$older->id.'"',
        ], false);
    }

    public function test_search_results_follow_the_same_release_order(): void
    {
        $newest = $this->uploadedComic('Match Newest XYZ', now()->subHour(), now()->subDays(30));
        $older = $this->uploadedComic('Match Older XYZ', null, now()->subDay());

        $this->get('/comics/search?search=Match')->assertOk()->assertSeeInOrder([
            'data-comic-card="'.$newest->id.'"',
            'data-comic-card="'.$older->id.'"',
        ], false);
    }

    /**
     * A comic with its upload date rewound: $releasedAt null means it was
     * published the moment it was uploaded.
     */
    protected function uploadedComic(string $title, ?Carbon $releasedAt, Carbon $uploadedAt): Comic
    {
        $comic = Comic::factory()->create(['title' => $title, 'published_at' => $releasedAt]);
        $comic->created_at = $uploadedAt;
        $comic->save();

        return $comic;
    }
}
