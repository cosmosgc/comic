<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComicRequest extends FormRequest
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
        // Per-file cap (KB). The total-request cap is checked in the browser
        // before sending; nginx rejects oversized bodies with 413 before
        // Laravel ever runs, so this is the second line of defense (422).
        $maxFileKb = config('upload.max_file_mb', 10) * 1024;

        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'folder' => 'required_without:images|array',
            'folder.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:'.$maxFileKb,
            'images' => 'required_without:folder|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:'.$maxFileKb,
            'published_at' => 'nullable|date',
            'is_exclusive' => 'nullable|boolean',
        ];
    }
}
