<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Phone keyboards capitalise and add spaces; treat "  Grace@Mail.com " as grace@mail.com. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Closed (soft-deleted) accounts keep their email reserved: the database index covers them too.
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
            'country' => ['nullable', 'string', 'size:2'],
            'language' => ['nullable', Rule::in(array_keys(config('faithfellows.languages')))],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'email_consent' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['terms.accepted' => __('Please accept the Terms of Service and Privacy Policy to continue.')];
    }
}
