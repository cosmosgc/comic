<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
            'text' => 'nullable|string|max:280',
            'media' => 'nullable|array|max:4',
            'media.*' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4048',
            'referenced_post_id' => 'nullable|exists:posts,id',
            'parent_id' => 'nullable|exists:posts,id',
        ];
    }
}
