<?php

namespace Tests\Feature;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\UserContactMethod;
use Tests\TestCase;

class ContactMethodsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['cache.default' => 'array']);
        config(['marketplace.enabled' => true]);

        $this->artisan('migrate');
    }

    public function test_profile_edit_renders_contact_rows_and_linked_accounts_section(): void
    {
        config([
            'oauth.providers.google.client_id' => 'google-id',
            'oauth.providers.google.client_secret' => 'google-secret',
        ]);

        $user = User::factory()->create();
        UserContactMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'whatsapp',
            'value' => '5511999998888',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Formas de contato')
            ->assertSee('Contas conectadas')
            ->assertSee('Conectar')
            ->assertSee('5511999998888', false);
    }

    public function test_marketplace_create_page_lists_saved_contact_methods(): void
    {
        $seller = User::factory()->create();
        UserContactMethod::factory()->create([
            'user_id' => $seller->id,
            'type' => 'telegram',
            'value' => 'meu_user',
            'is_public' => false,
        ]);

        $this->actingAs($seller)
            ->get(route('marketplace.create'))
            ->assertOk()
            ->assertSee('Formas de contato neste anúncio')
            ->assertSee('meu_user', false)
            ->assertSee('oculto no perfil', false);
    }

    public function test_profile_saves_multiple_contact_methods_and_normalises_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'contact_methods' => [
                [
                    'type' => 'whatsapp',
                    'value' => '+55 (11) 99999-8888',
                    'label' => 'Pessoal',
                    'is_public' => '1',
                ],
                [
                    'type' => 'telegram',
                    'value' => 'https://t.me/meu_user',
                    'is_public' => '1',
                ],
                [
                    'type' => 'github',
                    'value' => '@octocat',
                    'is_public' => '1',
                ],
            ],
        ])->assertRedirect(route('profile.show'));

        $methods = $user->fresh()->contactMethods;

        $this->assertCount(3, $methods);
        $this->assertSame('5511999998888', $methods[0]->value);
        $this->assertSame('meu_user', $methods[1]->value);
        $this->assertSame('octocat', $methods[2]->value);
        $this->assertSame('Pessoal', $methods[0]->label);
    }

    public function test_hidden_contact_method_appears_only_to_the_owner(): void
    {
        $user = User::factory()->create(['name' => 'Visible Person']);
        $public = UserContactMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'telegram',
            'value' => 'public_user',
            'is_public' => true,
        ]);
        $hidden = UserContactMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'telegram',
            'value' => 'hidden_user',
            'is_public' => false,
        ]);

        $url = route('profile.public.show.id', ['id' => $user->id]);

        $this->get($url)
            ->assertOk()
            ->assertSee($public->displayValue(), false)
            ->assertDontSee($hidden->displayValue(), false);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee($hidden->displayValue(), false)
            ->assertSee('oculto', false);
    }

    public function test_invalid_contact_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'contact_methods' => [
                ['type' => 'whatsapp', 'value' => 'não é telefone', 'is_public' => '1'],
            ],
        ])->assertSessionHasErrors('contact_methods.0.value');

        $this->assertDatabaseCount('user_contact_methods', 0);
    }

    public function test_removing_a_contact_method_from_the_form_deletes_it(): void
    {
        $user = User::factory()->create();
        $method = UserContactMethod::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseMissing('user_contact_methods', ['id' => $method->id]);
    }

    public function test_listing_can_select_multiple_saved_contact_methods(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $category = MarketplaceCategory::factory()->create();

        $whatsapp = UserContactMethod::factory()->create([
            'user_id' => $seller->id,
            'type' => 'whatsapp',
            'value' => '5511999998888',
            'is_public' => false,
        ]);
        $telegram = UserContactMethod::factory()->create([
            'user_id' => $seller->id,
            'type' => 'telegram',
            'value' => 'meu_user',
        ]);

        $this->actingAs($seller)->post(route('marketplace.store'), $this->listingPayload($category, [
            'contact_mode' => 'in_app_and_contacts',
            'contact_methods' => [$whatsapp->id, $telegram->id],
        ]))->assertRedirect();

        $listing = MarketplaceListing::query()->latest('id')->firstOrFail();

        $this->assertSame(
            [$whatsapp->id, $telegram->id],
            $listing->contactMethods->pluck('id')->all()
        );

        $this->actingAs($buyer)
            ->get(route('marketplace.show', ['listing' => $listing->slug]))
            ->assertOk()
            ->assertSee('wa.me/5511999998888', false)
            ->assertSee('t.me/meu_user', false);
    }

    public function test_contacts_only_listing_requires_at_least_one_method(): void
    {
        $seller = User::factory()->create();
        $category = MarketplaceCategory::factory()->create();

        $this->actingAs($seller)->post(route('marketplace.store'), $this->listingPayload($category, [
            'contact_mode' => 'contacts_only',
        ]))->assertSessionHasErrors('contact_methods');
    }

    public function test_listing_cannot_use_another_users_contact_method(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();
        $category = MarketplaceCategory::factory()->create();
        $foreign = UserContactMethod::factory()->create(['user_id' => $other->id]);

        $this->actingAs($seller)->post(route('marketplace.store'), $this->listingPayload($category, [
            'contact_mode' => 'in_app_and_contacts',
            'contact_methods' => [$foreign->id],
        ]))->assertSessionHasErrors('contact_methods.0');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function listingPayload(MarketplaceCategory $category, array $overrides = []): array
    {
        return array_merge([
            'marketplace_category_id' => $category->id,
            'kind' => 'physical',
            'title' => 'Item de teste para contato',
            'description' => 'Descrição suficientemente longa para o anúncio de teste.',
            'condition' => 'good',
            'listing_type' => 'sale',
            'price' => '10.00',
            'quantity' => 1,
            'shipping' => 'arrange',
            'contact_mode' => 'in_app',
        ], $overrides);
    }
}
