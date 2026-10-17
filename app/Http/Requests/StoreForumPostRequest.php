<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumPostRequest extends FormRequest
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
            'body' => 'required|string|min:1|max:20000',
            'parent_id' => 'nullable|integer|exists:forum_posts,id',
            'attachments' => 'nullable|array|max:'.(int) config('forum.max_attachments', 4),
            'attachments.*' => 'image|max:4096',
        ];
    }
}
