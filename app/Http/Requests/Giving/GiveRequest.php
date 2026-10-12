<?php

namespace App\Http\Requests\Giving;

use App\Models\GivingChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $amount = preg_replace('/[^\d.]/', '', (string) ($this->input('custom_amount') ?: $this->input('amount')));
        $this->merge(['amount' => $amount === '' ? null : $amount]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $church = $this->route('church');
        $categories = array_diff(array_keys(config('faithfellows.giving_categories')), ['event', 'pledge']);

        return [
            'amount' => ['required', 'numeric', 'min:500', 'max:100000000'],
            'category' => ['required_without:campaign_id', 'nullable', Rule::in($categories)],
            'method' => ['required', Rule::in(array_merge(GivingChannel::ONLINE, ['bank']))],
            'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', 'regex:/^(\+?256|0)?7\d{8}$/'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('church_id', $church->id)],
            'campaign_team_id' => ['nullable', Rule::exists('campaign_teams', 'id')->where('church_id', $church->id)],
            'pledge_id' => ['nullable', Rule::exists('pledges', 'id')->where('church_id', $church->id)->where('user_id', $this->user()->id)],
            'is_anonymous' => ['boolean'],
            'show_on_leaderboard' => ['boolean'],
            'recurring' => ['nullable', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a Ugandan mobile money number, e.g. 0772 123 456.'),
            'phone.required_if' => __('Enter the mobile money number to charge.'),
            'amount.min' => __('The minimum gift is UGX 500.'),
        ];
    }
}
