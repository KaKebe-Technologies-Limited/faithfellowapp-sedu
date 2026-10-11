<?php

namespace App\Http\Requests\Church;

use Illuminate\Foundation\Http\FormRequest;

class ChurchApplicationRequest extends FormRequest
{
    use ChurchProfileRules;

    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->churchProfileRules(),
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\s()-]{7,32}$/'],
            'application_notes' => ['nullable', 'string', 'max:2000'],
            'agree' => ['accepted'],
        ];
    }
}
