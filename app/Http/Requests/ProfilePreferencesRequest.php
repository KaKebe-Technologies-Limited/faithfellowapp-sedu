<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quiet_hours_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_end'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'digest_mode' => ['boolean'],
            'low_bandwidth' => ['boolean'],
            'directory_opt_in' => ['boolean'],
            'email_consent' => ['boolean'],
            'two_factor_enabled' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quiet_hours_start' => $this->input('quiet_hours_start') ?: null,
            'quiet_hours_end' => $this->input('quiet_hours_end') ?: null,
        ]);
    }
}
