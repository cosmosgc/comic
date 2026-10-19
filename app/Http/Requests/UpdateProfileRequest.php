<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$this->user()->id,
            'password' => 'nullable|string|min:8|confirmed',
            'avatar_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048|dimensions:max_width=5000,max_height=5000', // Limit to 2MB
            'banner_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096|dimensions:max_width=8000,max_height=8000', // Limit to 4MB
            'remove_banner' => 'nullable|boolean',
            'bio' => 'nullable|string',
            'links' => 'nullable|array',
            'links.*' => 'url', // Each link should be valid URL
            'show_liked_posts' => 'nullable|boolean',
            'forum_digest' => 'nullable|boolean',
            'showcase_badges' => 'nullable|array',
            'showcase_badges.*' => 'string|max:50',
        ];
    }
}
