<?php

namespace App\Http\Requests\Church;

use Illuminate\Validation\Rule;

/**
 * Validation rules shared by every form that edits a church profile.
 */
trait ChurchProfileRules
{
    /**
     * @return array<string, mixed>
     */
    protected function churchProfileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'denomination' => ['nullable', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'about' => ['nullable', 'string', 'max:20000'],
            'pastor_name' => ['nullable', 'string', 'max:120'],
            'founded_year' => ['nullable', 'integer', 'between:1800,'.date('Y')],
            'languages' => ['nullable', 'array'],
            'languages.*' => [Rule::in(array_keys(config('faithfellows.languages')))],
            'country' => ['nullable', 'string', 'size:2'],
            'district' => ['nullable', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
    }
}
