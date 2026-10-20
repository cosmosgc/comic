<?php

namespace App\Http\Requests;

class UpdateMarketplaceListingRequest extends StoreMarketplaceListingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'marketplace_listing_id.exists' => 'Anúncio inválido.',
        ];
    }
}
