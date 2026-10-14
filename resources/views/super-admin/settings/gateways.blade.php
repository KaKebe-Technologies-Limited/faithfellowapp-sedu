<x-layouts.super-admin :title="__('Payment gateways')">
    <x-ff.page-header :title="__('Payment gateways')" :subtitle="__('Health of each payment provider over the last 30 days. Switch a gateway off during an outage — givers will see the other options.')"
        :breadcrumbs="[__('Settings') => route('super-admin.settings'), __('Payment gateways') => null]" />

    @if ($sandboxForced)
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-cone-striped"></i><div>{{ __('PAYMENTS_SANDBOX is on: every gateway is simulated and no real money moves.') }}</div></div>
    @endif
    @if ($pending)
        <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i><div>{{ trans_choice(':count payment has been pending for over 30 minutes. ff:payments-reconcile re-checks them every 5 minutes.|:count payments have been pending for over 30 minutes. ff:payments-reconcile re-checks them every 5 minutes.', $pending) }}</div></div>
    @endif

    <div class="row g-3">
        @foreach ($gateways as $g)
            @php($s = $g['stats'])
            <div class="col-md-6 col-xl-4">
                <div @class(['ff-card h-100 d-flex flex-column gap-2', 'opacity-75' => ! $g['enabled']])>
                    <div class="d-flex align-items-center gap-2">
                        <h2 class="h6 mb-0 flex-grow-1">{{ $g['label'] }}</h2>
                        @if ($g['sandbox'])<span class="ff-badge ff-badge-warning">{{ __('Sandbox') }}</span>@else<span class="ff-badge ff-badge-success">{{ __('Live') }}</span>@endif
                        @unless ($g['enabled'])<span class="ff-badge ff-badge-danger">{{ __('Off') }}</span>@endunless
                    </div>
                    <div class="row g-0 text-center border-top border-bottom py-2 small">
                        <div class="col"><strong class="d-block text-ff-black">{{ number_format($s->total ?? 0) }}</strong>{{ __('attempts') }}</div>
                        <div class="col"><strong class="d-block text-ff-black">{{ $s && $s->total ? round($s->ok / $s->total * 100) : 0 }}%</strong>{{ __('success') }}</div>
                        <div class="col"><strong class="d-block text-ff-black">{{ ff_short_number($s->volume ?? 0) }}</strong>{{ __('UGX') }}</div>
                    </div>
                    <div class="small text-ff-muted">{{ __('Last successful payment:') }} {{ $s?->last_paid ? \Illuminate\Support\Carbon::parse($s->last_paid)->diffForHumans() : '—' }}</div>
                    <div class="small">
                        <span class="text-ff-muted">{{ __('Webhook URL') }}</span>
                        <div class="d-flex gap-1 align-items-center"><code class="small text-truncate">{{ $g['webhook'] }}</code><button type="button" class="btn btn-link btn-sm p-0" data-ff-copy="{{ $g['webhook'] }}" aria-label="{{ __('Copy') }}"><i class="bi bi-clipboard"></i></button></div>
                    </div>
                    <form method="POST" action="{{ route('super-admin.settings.gateways.toggle', $g['method']) }}" class="mt-auto" @if ($g['enabled']) data-ff-confirm="{{ __('Switch :g off for every church?', ['g' => $g['label']]) }}" @endif>@csrf @method('PUT')
                        <input type="hidden" name="enabled" value="{{ $g['enabled'] ? 0 : 1 }}">
                        <button @class(['btn btn-sm w-100', 'btn-ff-ghost text-danger' => $g['enabled'], 'btn-ff-primary' => ! $g['enabled']])>{{ $g['enabled'] ? __('Switch off') : __('Switch on') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <p class="small text-ff-muted mt-3">{{ __('Credentials are configured in .env (MTN_MOMO_*, AIRTEL_*, IOTEC_*, STRIPE_*). A gateway without credentials runs in sandbox mode automatically.') }}</p>
</x-layouts.super-admin>
