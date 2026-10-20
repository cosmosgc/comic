<?php

namespace Tests\Feature;

use App\Models\Comic;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceConversation;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingImage;
use App\Models\MarketplaceMessage;
use App\Models\MarketplaceReport;
use App\Models\MarketplaceTag;
use App\Models\MarketplaceWatch;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    protected User $seller;

    protected User $buyer;

    protected MarketplaceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        config(['marketplace.enabled' => true]);
        config(['marketplace.require_approval' => false]);
        config(['marketplace.max_active_listings_per_user' => 20]);

        $this->artisan('migrate');

        $this->seller = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->category = MarketplaceCategory::factory()->create(['name' => 'Figures', 'slug' => 'figures']);
    }

    public function test_home_and_browse_only_show_approved_active_listings(): void
    {
        $visible = MarketplaceListing::factory()->create(['marketplace_category_id' => $this->category->id]);
        $pending = MarketplaceListing::factory()->pending()->create(['marketplace_category_id' => $this->category->id]);
        $draft = MarketplaceListing::factory()->draft()->create(['marketplace_category_id' => $this->category->id]);

        $this->get('/marketplace')
            ->assertOk()
            ->assertSee($visible->title, false)
            ->assertDontSee($pending->title, false)
            ->assertDontSee($draft->title, false);

        $this->get('/marketplace/browse')
            ->assertOk()
            ->assertSee($visible->title, false)
            ->assertDontSee($pending->title, false);
    }

    public function test_hidden_or_pending_listing_is_not_public_but_the_owner_can_see_it(): void
    {
        $listing = MarketplaceListing::factory()->pending()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        $this->get(route('marketplace.show', ['listing' => $listing->slug]))->assertNotFound();

        $this->actingAs($this->seller)
            ->get(route('marketplace.show', ['listing' => $listing->slug]))
            ->assertOk()
            ->assertSee('aguardando aprovação', false);
    }

    public function test_guest_cannot_create_a_listing(): void
    {
        $this->get(route('marketplace.create'))->assertRedirect('/login');
        $this->post(route('marketplace.store'), [])->assertRedirect('/login');
    }

    public function test_member_can_create_a_full_listing_with_image_comic_and_tags(): void
    {
        $comic = Comic::factory()->create(['user_id' => $this->seller->id, 'title' => 'Meu comic']);
        $listingId = null;

        try {
            $this->actingAs($this->seller)->post(route('marketplace.store'), [
                'marketplace_category_id' => $this->category->id,
                'kind' => 'comic',
                'title' => 'Comic + print assinado',
                'description' => "Primeira linha.\nSegunda linha.",
                'condition' => 'like_new',
                'listing_type' => 'sale',
                'price' => '150.00',
                'price_negotiable' => '1',
                'quantity' => 1,
                'location_city' => 'São Paulo',
                'location_region' => 'SP',
                'location_country' => 'Brasil',
                'shipping' => 'ships',
                'contact_mode' => 'in_app',
                'tags' => 'arte, original, arte',
                'comics' => [$comic->id],
                'images' => [UploadedFile::fake()->image('capa.jpg', 800, 600)],
            ])->assertRedirect();

            $listing = MarketplaceListing::query()->latest('id')->firstOrFail();
            $listingId = $listing->id;

            $this->assertSame($this->seller->id, $listing->user_id);
            $this->assertSame(MarketplaceListing::STATUS_ACTIVE, $listing->status);
            $this->assertTrue($listing->is_approved);
            $this->assertSame('150.00', $listing->price);
            $this->assertTrue($listing->price_negotiable);
            $this->assertNotNull($listing->published_at);
            $this->assertNotNull($listing->expires_at);
            $this->assertSame([$comic->id], $listing->comics->pluck('id')->all());
            $this->assertSame(2, $listing->tags->count());
            $this->assertSame(1, $listing->images->count());
            $this->assertTrue(is_file(public_path($listing->images->first()->path)));

            $this->actingAs($this->buyer)
                ->get(route('marketplace.show', ['listing' => $listing->slug]))
                ->assertOk()
                ->assertSee('Comic + print assinado', false)
                ->assertSee('Primeira linha.', false);
        } finally {
            if ($listingId !== null) {
                $this->deleteListingFiles($listingId);
            }
        }
    }

    public function test_another_member_cannot_edit_or_delete_the_listing(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        $this->actingAs($this->buyer)
            ->get(route('marketplace.edit', ['listing' => $listing->slug]))
            ->assertForbidden();

        $this->actingAs($this->buyer)
            ->delete(route('marketplace.destroy', ['listing' => $listing->slug]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('marketplace_listings', ['id' => $listing->id]);
    }

    public function test_owner_can_edit_mark_as_sold_and_watchlist_toggles(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        $this->actingAs($this->seller)->put(route('marketplace.update', ['listing' => $listing->slug]), [
            'marketplace_category_id' => $this->category->id,
            'kind' => 'physical',
            'title' => 'Título atualizado',
            'description' => 'Descrição atualizada com detalhes.',
            'condition' => 'good',
            'listing_type' => 'sale',
            'price' => '80',
            'quantity' => 1,
            'shipping' => 'arrange',
            'contact_mode' => 'in_app',
        ])->assertRedirect(route('marketplace.show', ['listing' => $listing->slug]));

        $listing->refresh();
        $this->assertSame('Título atualizado', $listing->title);

        $this->actingAs($this->buyer)->post(route('marketplace.watch', ['listing' => $listing->slug]))
            ->assertRedirect();
        $this->assertDatabaseHas('marketplace_watches', [
            'user_id' => $this->buyer->id,
            'marketplace_listing_id' => $listing->id,
        ]);
        $this->assertSame(1, MarketplaceWatch::count());

        $this->actingAs($this->buyer)->post(route('marketplace.watch', ['listing' => $listing->slug]))
            ->assertRedirect();
        $this->assertSame(0, MarketplaceWatch::count());

        $this->actingAs($this->seller)->post(route('marketplace.status', ['listing' => $listing->slug]), [
            'action' => 'sold',
        ])->assertRedirect();

        $this->assertSame(MarketplaceListing::STATUS_SOLD, $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->sold_at);
    }

    public function test_contact_starts_a_conversation_and_reply_updates_unread_counts(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        $this->actingAs($this->buyer)->post(route('marketplace.contact', ['listing' => $listing->slug]), [
            'body' => 'Ainda está disponível?',
        ])->assertRedirect();

        $conversation = MarketplaceConversation::sole();
        $this->assertSame($this->buyer->id, $conversation->buyer_id);
        $this->assertSame($this->seller->id, $conversation->seller_id);
        $this->assertSame(1, $conversation->seller_unread_count);
        $this->assertSame(1, MarketplaceMessage::count());
        $this->assertSame(1, $listing->fresh()->inquiry_count);

        // A second contact reuses the thread instead of creating a new one.
        $this->actingAs($this->buyer)->post(route('marketplace.contact', ['listing' => $listing->slug]), [
            'body' => 'Consigo retirar amanhã?',
        ])->assertRedirect();
        $this->assertSame(1, MarketplaceConversation::count());
        $this->assertSame(2, MarketplaceMessage::count());

        // Opening as the seller marks the buyer's messages read.
        $this->actingAs($this->seller)
            ->get(route('marketplace.messages.show', ['conversation' => $conversation]))
            ->assertOk()
            ->assertSee('Ainda está disponível?', false);
        $this->assertSame(0, $conversation->fresh()->seller_unread_count);
        $this->assertSame(0, MarketplaceMessage::whereNull('read_at')->count());

        $this->actingAs($this->seller)->post(route('marketplace.messages.store', ['conversation' => $conversation]), [
            'body' => 'Está sim! Pode vir buscar.',
        ])->assertRedirect();
        $this->assertSame(1, $conversation->fresh()->buyer_unread_count);
    }

    public function test_report_and_category_page_work(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        $this->actingAs($this->buyer)->post(route('marketplace.report', ['listing' => $listing->slug]), [
            'reason' => 'scam',
            'details' => 'Pediu pagamento adiantado.',
        ])->assertRedirect();

        $report = MarketplaceReport::sole();
        $this->assertSame($this->buyer->id, $report->reporter_id);
        $this->assertSame($listing->id, $report->marketplace_listing_id);
        $this->assertSame('pending', $report->status);

        $this->get(route('marketplace.category', ['categorySlug' => $this->category->slug]))
            ->assertOk()
            ->assertSee($listing->title, false)
            ->assertSee('Figures', false);
    }

    public function test_search_finds_title_description_tag_and_seller(): void
    {
        $tag = MarketplaceTag::create(['name' => 'raro', 'slug' => 'raro']);

        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
            'title' => 'Figure raríssima',
            'description' => 'Item de colecionador',
        ]);
        $listing->tags()->attach($tag);

        $this->get(route('marketplace.search', ['q' => 'raríssima']))
            ->assertOk()
            ->assertSee('Figure raríssima', false);

        $this->get(route('marketplace.search', ['q' => 'raro']))
            ->assertOk()
            ->assertSee('Figure raríssima', false);

        $this->get(route('marketplace.search', ['q' => $this->seller->name]))
            ->assertOk()
            ->assertSee('Figure raríssima', false);
    }

    public function test_require_approval_holds_a_new_listing_for_moderation(): void
    {
        config(['marketplace.require_approval' => true]);

        $this->actingAs($this->seller)->post(route('marketplace.store'), [
            'marketplace_category_id' => $this->category->id,
            'kind' => 'physical',
            'title' => 'Anúncio em análise',
            'description' => 'Descrição longa o suficiente.',
            'condition' => 'good',
            'listing_type' => 'sale',
            'price' => '25',
            'shipping' => 'arrange',
            'contact_mode' => 'in_app',
        ])->assertRedirect();

        $listing = MarketplaceListing::query()->latest('id')->firstOrFail();
        $this->assertFalse($listing->is_approved);
        $this->assertSame(MarketplaceListing::STATUS_ACTIVE, $listing->status);

        $this->get(route('marketplace.show', ['listing' => $listing->slug]))
            ->assertOk()
            ->assertSee('aguardando aprovação', false);

        auth()->logout();

        $this->get(route('marketplace.show', ['listing' => $listing->slug]))->assertNotFound();
    }

    public function test_authenticated_marketplace_screens_render(): void
    {
        $listing = MarketplaceListing::factory()->create([
            'marketplace_category_id' => $this->category->id,
            'user_id' => $this->seller->id,
        ]);

        MarketplaceWatch::create([
            'user_id' => $this->seller->id,
            'marketplace_listing_id' => $listing->id,
        ]);

        $conversation = MarketplaceConversation::create([
            'marketplace_listing_id' => $listing->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'subject' => $listing->title,
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'sender_id' => $this->buyer->id,
            'body' => 'Olá!',
        ]);

        $this->actingAs($this->seller)
            ->get(route('marketplace.create'))->assertOk()->assertSee('Criar anúncio', false);
        $this->actingAs($this->seller)
            ->get(route('marketplace.mine'))->assertOk()->assertSee($listing->title, false);
        $this->actingAs($this->seller)
            ->get(route('marketplace.watching'))->assertOk()->assertSee($listing->title, false);
        $this->actingAs($this->seller)
            ->get(route('marketplace.edit', ['listing' => $listing->slug]))->assertOk()->assertSee('Editar anúncio', false);
        $this->actingAs($this->seller)
            ->get(route('marketplace.messages'))->assertOk()->assertSee('Olá!', false);
    }

    protected function deleteListingFiles(int $listingId): void
    {
        $paths = MarketplaceListingImage::query()
            ->where('marketplace_listing_id', $listingId)
            ->pluck('path');

        foreach ($paths as $path) {
            ImageStore::delete($path);
        }

        $directory = public_path('storage/marketplace/listings');
        if (is_dir($directory) && count(File::files($directory)) === 0) {
            @rmdir($directory);
        }
    }
}
