<?php

namespace App\Http\Requests;

use App\Models\MarketplaceListing;
use App\Support\Marketplace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarketplaceListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contactModes = (array) config('marketplace.contact_modes', ['in_app']);

        return [
            'marketplace_category_id' => [
                'required',
                Rule::exists('marketplace_categories', 'id')->where('is_visible', true),
            ],
            'kind' => ['required', Rule::in(array_keys(MarketplaceListing::kindOptions()))],
            'title' => 'required|string|min:5|max:120',
            'description' => 'required|string|min:10|max:5000',
            'condition' => ['required', Rule::in(array_keys(MarketplaceListing::conditionOptions()))],
            'listing_type' => ['required', Rule::in(array_keys(MarketplaceListing::typeOptions()))],
            'price' => 'nullable|numeric|min:0|max:9999999.99',
            'price_negotiable' => 'nullable|boolean',
            'quantity' => 'nullable|integer|min:1|max:999',
            'location_city' => 'nullable|string|max:80',
            'location_region' => 'nullable|string|max:80',
            'location_country' => 'nullable|string|max:60',
            'shipping' => ['required', Rule::in(array_keys(MarketplaceListing::shippingOptions()))],
            'contact_mode' => ['required', Rule::in($contactModes)],
            'contact_email' => 'nullable|required_if:contact_mode,in_app_and_email,email_only|email|max:190',
            'contact_methods' => [
                'nullable',
                'array',
                'max:10',
                Rule::requiredIf(fn () => ! $this->boolean('draft')
                    && in_array($this->input('contact_mode'), ['in_app_and_contacts', 'contacts_only'], true)),
            ],
            'contact_methods.*' => [
                'integer',
                'distinct',
                Rule::exists('user_contact_methods', 'id')->where('user_id', (int) $this->user()?->id),
            ],
            'tags' => 'nullable|string|max:240',
            'images' => 'nullable|array|max:'.Marketplace::maxImages(),
            'images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'comics' => 'nullable|array|max:10',
            'comics.*' => [
                'integer',
                'distinct',
                Rule::exists('comics', 'id')->where('user_id', (int) $this->user()?->id),
            ],
            'draft' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marketplace_category_id.required' => 'Escolha uma categoria.',
            'contact_email.required_if' => 'Informe o e-mail que os compradores devem usar.',
            'contact_methods.required' => 'Escolha ao menos uma forma de contato para este anúncio.',
            'contact_methods.*.exists' => 'Você só pode usar formas de contato da sua conta.',
            'comics.*.exists' => 'Você só pode vincular comics seus.',
            'images.*.image' => 'Cada arquivo precisa ser uma imagem.',
            'images.*.max' => 'Cada imagem pode ter no máximo 4 MB.',
        ];
    }
}
