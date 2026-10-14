<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot: bots fill every field.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['website.prohibited' => __('Spam detected.')];
    }
}
