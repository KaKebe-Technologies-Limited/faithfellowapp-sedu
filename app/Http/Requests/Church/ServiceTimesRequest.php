<?php

namespace App\Http\Requests\Church;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceTimesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $churchId = $this->route('church')->id;

        return [
            'services' => ['array', 'max:30'],
            'services.*.name' => ['required', 'string', 'max:120'],
            'services.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'services.*.start_time' => ['required', 'date_format:H:i'],
            'services.*.end_time' => ['nullable', 'date_format:H:i', 'after:services.*.start_time'],
            'services.*.language' => ['nullable', Rule::in(array_keys(config('faithfellows.languages')))],
            'services.*.branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $churchId)],
            'services.*.location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
