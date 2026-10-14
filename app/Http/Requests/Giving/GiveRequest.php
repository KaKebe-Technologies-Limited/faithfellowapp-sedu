<?php

namespace App\Http\Requests\Giving;

use App\Models\GivingChannel;
use App\Services\PaymentService;
use App\Support\MobileMoneyPhone;
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
            'method' => ['required', Rule::in(array_merge(GivingChannel::ONLINE, ['bank'])), function ($attribute, $value, $fail) {
                if (! PaymentService::enabled((string) $value)) {
                    $fail(__('This payment method is temporarily unavailable. Please choose another.'));
                }
            }],
            'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', MobileMoneyPhone::rule()],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('church_id', $church->id)],
            'campaign_team_id' => ['nullable', Rule::exists('campaign_teams', 'id')->where('church_id', $church->id)],
            'pledge_id' => ['nullable', Rule::exists('pledges', 'id')->where('church_id', $church->id)->where('user_id', $this->user()->id)],
            'small_group_id' => ['nullable', Rule::exists('small_groups', 'id')->where('church_id', $church->id)],
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
