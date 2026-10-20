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
            'type' => 'nullable|in:discussion,question,poll',
            'poll_question' => 'required_if:type,poll|nullable|string|min:3|max:200',
            'poll_options' => 'required_if:type,poll|nullable|array|min:2|max:10',
            'poll_options.*' => 'nullable|string|max:120',
            'poll_multiple' => 'nullable|boolean',
            'poll_closes_at' => 'nullable|date|after:now',
            'tags' => 'nullable|array|max:5',
            'tags.*' => 'string|max:30',
            'attachments' => 'nullable|array|max:'.(int) config('forum.max_attachments', 4),
            'attachments.*' => 'image|max:4096',
            // Premium (planned): topic banners will be a paid feature; public for now.
            'banner' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'banner_position_x' => 'nullable|integer|min:0|max:100',
            'banner_position_y' => 'nullable|integer|min:0|max:100',
        ];
    }
}
