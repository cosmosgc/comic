<?php

namespace App\Http\Requests;

use App\Support\ContactMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop completely empty new rows so a stray "add contact" click does
     * not fail the whole profile update.
     */
    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('contact_methods', []))
            ->map(function ($row) {
                if (is_array($row)
                    && is_string($row['type'] ?? null)
                    && ContactMethod::exists($row['type'])
                    && is_string($row['value'] ?? null)) {
                    $row['value'] = ContactMethod::normalize($row['type'], $row['value']);
                }

                return $row;
            })
            ->filter(fn ($row) => is_array($row) && (
                filled($row['id'] ?? null)
                || filled($row['type'] ?? null)
                || filled($row['value'] ?? null)
                || filled($row['label'] ?? null)
            ))
            ->values()
            ->all();

        $this->merge(['contact_methods' => $rows]);
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
            'contact_methods' => 'nullable|array|max:10',
            'contact_methods.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('user_contact_methods', 'id')->where('user_id', $this->user()->id),
            ],
            'contact_methods.*.type' => ['required', Rule::in(array_keys(ContactMethod::types()))],
            'contact_methods.*.value' => 'required|string|max:190',
            'contact_methods.*.label' => 'nullable|string|max:60',
            'contact_methods.*.is_public' => 'nullable|boolean',
            'show_liked_posts' => 'nullable|boolean',
            'forum_digest' => 'nullable|boolean',
            'showcase_badges' => 'nullable|array',
            'showcase_badges.*' => 'string|max:50',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_methods.max' => 'Você pode salvar até 10 formas de contato.',
            'contact_methods.*.type.required' => 'Escolha o tipo de cada contato.',
            'contact_methods.*.value.required' => 'Informe o dado de contato.',
            'contact_methods.*.id.exists' => 'Um dos contatos não pertence à sua conta.',
        ];
    }

    /**
     * Value rules depend on the selected type (phone, handle, e-mail...).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('contact_methods', []) as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $type = $row['type'] ?? null;
                    $value = $row['value'] ?? null;

                    if (! is_string($type) || ! ContactMethod::exists($type)
                        || ! is_string($value) || trim($value) === '') {
                        continue;
                    }

                    $item = validator(
                        ['value' => $value],
                        ['value' => ContactMethod::valueRules($type)]
                    );

                    if ($item->fails()) {
                        foreach ($item->errors()->get('value') as $message) {
                            $validator->errors()->add('contact_methods.'.$index.'.value', $message);
                        }
                    }
                }
            },
        ];
    }
}
