<x-layouts.church-admin :church="$church" :title="__('Giving channels')">
    <x-ff.page-header :title="__('Settings')" />
    @include('church-admin.settings._nav')

    @php
        $meta = [
            'mtn_momo' => [__('MTN MoMo'), 'bi-phone', __('Members approve a prompt on their MTN line.'), ['merchant_code' => __('Merchant / MoMoPay code (optional)')]],
            'airtel_money' => [__('Airtel Money'), 'bi-phone', __('Members approve a prompt on their Airtel line.'), ['merchant_code' => __('Merchant code (optional)')]],
            'iotec' => [__('Iotec (MTN + Airtel)'), 'bi-lightning-charge', __('One integration for both networks via Iotec Pay.'), []],
            'stripe' => [__('Card (Visa / Mastercard)'), 'bi-credit-card', __('Best for diaspora givers. Secure Stripe Checkout.'), []],
            'bank' => [__('Bank transfer'), 'bi-bank', __('Shown to members with your account details; confirmed by reconciliation.'), ['bank_name' => __('Bank name'), 'account_name' => __('Account name'), 'account_number' => __('Account number'), 'branch' => __('Branch'), 'swift' => __('SWIFT code')]],
            'ussd' => [__('USSD (feature phones)'), 'bi-telephone', __('Members dial the FaithFellows USSD code and enter your church code: :code', ['code' => $church->id]), []],
            'cash' => [__('Cash'), 'bi-cash', __('Finance staff record cash offerings and issue receipts.'), []],
        ];
    @endphp

    <form method="POST" action="{{ route('church-admin.settings.giving.update', $church) }}" novalidate>
        @csrf @method('PUT')
        @if (request('wizard'))<input type="hidden" name="wizard" value="1">@endif
        <div class="row g-3">
            @foreach ($channels as $type => $channel)
                @php([$label, $icon, $help, $fields] = $meta[$type])
                <div class="col-lg-6">
                    <div class="ff-card h-100" x-data="{ on: {{ old("channels.$type.is_active", $channel?->is_active) ? 'true' : 'false' }} }">
                        <div class="d-flex align-items-start gap-3">
                            <span class="ff-feature-icon mb-0"><i class="bi {{ $icon }}"></i></span>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <h2 class="h6 mb-0">{{ $label }}</h2>
                                    @if ($sandbox[$type])<span class="ff-badge ff-badge-warning">{{ __('Sandbox') }}</span>@endif
                                </div>
                                <p class="small text-ff-muted mb-2">{{ $help }}</p>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="channels[{{ $type }}][is_active]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="channels[{{ $type }}][is_active]" value="1" x-model="on" aria-label="{{ __('Enable :c', ['c' => $label]) }}">
                            </div>
                        </div>
                        <div x-show="on" x-cloak>
                            <x-ff.input :name="'channels['.$type.'][label]'" :label="__('Display name (optional)')" :value="$channel?->label" :placeholder="$label" />
                            @foreach ($fields as $field => $fieldLabel)
                                <x-ff.input :name="'channels['.$type.'][details]['.$field.']'" :label="$fieldLabel" :value="$channel?->details[$field] ?? null" />
                            @endforeach
                            <x-ff.textarea :name="'channels['.$type.'][instructions]'" :label="__('Instructions for givers (optional)')" :value="$channel?->instructions" rows="2" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="ff-card ff-card-mist mt-4 d-flex flex-column flex-md-row gap-3 align-items-md-center">
            <div class="flex-grow-1 small">
                <i class="bi bi-shield-check text-ff-primary"></i>
                {{ __('Gateway credentials (MTN, Airtel, Iotec, Stripe) are managed securely by the FaithFellows team. Gifts settle to your church account — track them under Finance → Payouts.') }}
            </div>
            <button class="btn btn-ff-primary px-4">{{ request('wizard') ? __('Save & continue') : __('Save giving channels') }}</button>
        </div>
    </form>
</x-layouts.church-admin>
