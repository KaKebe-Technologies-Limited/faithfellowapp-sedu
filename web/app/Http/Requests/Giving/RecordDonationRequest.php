<?php

namespace App\Http\Requests\Giving;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Finance staff entering cash / cheque / bank gifts by hand.
 */
class RecordDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'giving.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $church = $this->route('church');

        return [
            'amount' => ['required', 'numeric', 'min:100', 'max:1000000000'],
            'category' => ['required', Rule::in(array_keys(config('faithfellows.giving_categories')))],
            'method' => ['required', Rule::in(['cash', 'bank'])],
            'giver_name' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', Rule::exists('subscriptions', 'user_id')->where('church_id', $church->id)],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('church_id', $church->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'is_anonymous' => ['boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
