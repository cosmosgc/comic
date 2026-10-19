<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Page image upload, shared by the reader "add page" form and the comic
 * editor. webp is allowed because the upload page recompresses big
 * images to webp in-browser.
 */
class StorePageRequest extends FormRequest
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
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.(config('upload.max_file_mb', 10) * 1024),
        ];
    }
}
