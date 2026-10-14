<?php

namespace App\Http\Requests\Church;

use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChurchProfileRequest extends FormRequest
{
    use ChurchProfileRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('church'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');

        return [
            ...$this->churchProfileRules(),
            'slug' => ['sometimes', 'required', 'alpha_dash:ascii', 'min:3', 'max:60', 'lowercase', Rule::unique('churches', 'slug')->ignore($church->id)],
            'requires_member_approval' => ['sometimes', 'boolean'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'url', 'max:255'],
        ];
    }
}
