<?php

namespace App\Http\Requests\SuperAdmin;

use App\Http\Requests\Church\ChurchProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreChurchRequest extends FormRequest
{
    use ChurchProfileRules;

    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->churchProfileRules(),
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
            'activate' => ['boolean'],
        ];
    }
}
