<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Collection create/update payload. Both endpoints accept the same
 * shape, so the rules live here once (create defaults `is_public` on,
 * update defaults it off — that difference is controller logic).
 */
class CollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
            // Validated before the collection is written, so a bogus
            // comic id can never leave an empty or half-updated
            // collection behind.
            'comics' => 'nullable|array',
            'comics.*' => 'integer|exists:comics,id',
        ];
    }
}
