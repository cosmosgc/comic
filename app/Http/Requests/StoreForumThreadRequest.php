<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumThreadRequest extends FormRequest
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
            'title' => 'required|string|min:3|max:150',
            'body' => 'required|string|min:2|max:20000',
            'type' => 'nullable|in:discussion,question',
            'tags' => 'nullable|array|max:5',
            'tags.*' => 'string|max:30',
            'attachments' => 'nullable|array|max:'.(int) config('forum.max_attachments', 4),
            'attachments.*' => 'image|max:4096',
        ];
    }
}
