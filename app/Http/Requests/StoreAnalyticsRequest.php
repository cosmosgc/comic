<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalyticsRequest extends FormRequest
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
            'url' => 'required|string|max:2000',
            'user_agent' => 'required|string|max:1000',
            'event_type' => 'required|string|max:50',
            'device_type' => 'required|string|max:50',
            'referral_source' => 'nullable|string|max:2000',
            'campaign' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:0|max:86400',
            'browser' => 'nullable|string|max:100',
            'os' => 'nullable|string|max:100',
        ];
    }
}
