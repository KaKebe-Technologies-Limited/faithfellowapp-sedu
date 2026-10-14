<?php

namespace App\Http\Requests\Church;

use App\Models\ChurchRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChurchRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'roles.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(ChurchRole::catalog())],
        ];
    }
}
