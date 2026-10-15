<x-layouts.church-admin :church="$church" :title="__('Billing & plan')">
    <x-ff.page-header :title="__('Billing & plan')" :subtitle="__('Your FaithFellows subscription, usage and invoices.')" />

    {{-- The next payment, always in view: when, how much, and what happens if it is missed. --}}
    @php($tone = ['overdue' => 'danger', 'suspended' => 'danger', 'free' => 'success', 'paid' => 'success'][$next['state']] ?? 'success')
    <div class="ff-card mb-4 border-start border-4 border-{{ $tone }}">
        <div class="row g-3 align-items-center">
            <div class="col-md-7">
                <div class="small text-ff-muted text-uppercase fw-semibold">{{ $next['state'] === 'free' ? __('Free until') : ($next['state'] === 'overdue' ? __('Payment overdue since') : __('Next payment')) }}</div>
                <div class="fs-3 fw-bold text-ff-black">
                    {{ $next['date']?->format('l, j F Y') ?? '—' }}
                </div>
                <div class="small text-ff-muted">
                    @if ($next['date'] === null) {{ __('Your first invoice is issued before the free period ends.') }}
                    @elseif ($next['days'] > 0) {{ trans_choice('in :n day|in :n days', $next['days'], ['n' => $next['days']]) }}
                    @elseif ($next['days'] === 0) {{ __('today') }}
                    @else {{ trans_choice(':n day ago|:n days ago', abs($next['days']), ['n' => abs($next['days'])]) }} @endif
                    · {{ __(':amount every :interval', ['amount' => ff_money($next['amount'], $next['currency']), 'interval' => __($next['interval'])]) }}
                </div>
                <p class="small text-ff-muted mt-2 mb-0">{{ __('This monthly fee covers the servers, the daily running of the platform and support, so your church portal and the app keep working. We email you a week before each payment day.') }}</p>
            </div>
            <div class="col-md-5 text-md-end">
                @if ($next['invoice'])
                    <button class="btn btn-ff-primary btn-lg" data-bs-toggle="modal" data-bs-target="#pay{{ $next['invoice']->id }}"><i class="bi bi-phone"></i> {{ __('Pay :amount with mobile money', ['amount' => ff_money($next['invoice']->total, $next['invoice']->currency)]) }}</button>
                @else
                    <span class="small text-ff-muted"><i class="bi bi-check-circle text-success"></i> {{ __('Nothing to pay right now.') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="ff-card h-100 bg-ff-gradient border-0">
                <div class="small text-white-50 text-uppercase fw-semibold">{{ __('Current plan') }}</div>
                <div class="fs-3 fw-bold text-white">{{ $church->plan?->name ?? __('Starter') }}</div>
                <div class="text-white mb-3" style="opacity:.9">{{ ff_money($church->plan?->price ?? config('faithfellows.pricing.plan_price')) }} / {{ __($church->plan?->interval ?? 'month') }}</div>
                <div class="small text-white">
                    @switch($church->billing_status)
                        @case('trial') <i class="bi bi-gift"></i> {{ __('Free hosting until :d', ['d' => $church->trial_ends_at?->format('j F Y')]) }} @break
                        @case('active') <i class="bi bi-check-circle"></i> {{ __('Paid until :d', ['d' => $church->paid_until?->format('j F Y') ?? '—']) }} @break
                        @case('past_due') <i class="bi bi-exclamation-triangle"></i> {{ __('Payment overdue — please pay to avoid suspension.') }} @break
                        @case('suspended') <i class="bi bi-pause-circle"></i> {{ __('Suspended for non-payment. Paying the overdue invoice restores access instantly.') }} @break
                    @endswitch
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <x-ff.card :title="__('Usage')">
                @foreach ([
                    'storage' => [__('Media storage'), 'MB'],
                    'stream' => [__('Live stream hours (this month)'), 'h'],
                    'admins' => [__('Staff accounts'), ''],
                    'branches' => [__('Branches'), ''],
                ] as $key => [$label, $unit])
                    @php($u = $usage[$key])
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small"><span>{{ $label }}</span><span class="fw-semibold">{{ $u['used'] }}{{ $unit }} / {{ $u['limit'] }}{{ $unit }}</span></div>
                        <x-ff.progress :value="$u['limit'] ? $u['used'] / $u['limit'] * 100 : 0" size="sm" />
                    </div>
                @endforeach
            </x-ff.card>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar"><h2 class="h6 mb-0">{{ __('Invoices') }}</h2></div>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="fw-semibold text-ff-black">{{ $invoice->number }}</td>
                                    <td class="small">{{ $invoice->period_start->format('j M') }} – {{ $invoice->period_end->format('j M Y') }}</td>
                                    <td class="text-end fw-semibold">{{ ff_money($invoice->total, $invoice->currency) }}</td>
                                    <td class="small">{{ $invoice->due_date->format('j M Y') }}</td>
                                    <td><x-ff.status :status="$invoice->status" /></td>
                                    <td class="text-end">
                                        @if ($invoice->isPayable())
                                            <button class="btn btn-sm btn-ff-primary" data-bs-toggle="modal" data-bs-target="#pay{{ $invoice->id }}">{{ __('Pay') }}</button>
                                            @if ($invoice->method)
                                                <form method="POST" action="{{ route('church-admin.billing.check', [$church, $invoice]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Check payment') }}"><i class="bi bi-arrow-clockwise"></i></button></form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ff.empty-state icon="bi-receipt" :title="__('No invoices yet')" :message="__('Your first invoice is issued when free hosting ends.')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($invoices->hasPages())<div class="ff-table-footer">{{ $invoices->links() }}</div>@endif
            </div>
        </div>
        <div class="col-xl-4 d-grid gap-4 align-content-start">
            @if ($plans->count() > 1)
                <form method="POST" action="{{ route('church-admin.billing.plan', $church) }}" class="ff-card">@csrf
                    <h2 class="h6">{{ __('Change plan') }}</h2>
                    <x-ff.select name="plan_id" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.ff_money($p->price).'/'.$p->interval])" :value="$church->plan_id" />
                    <button class="btn btn-ff-outline w-100">{{ __('Switch plan') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('church-admin.billing.domain', $church) }}" class="ff-card">@csrf
                <h2 class="h6">{{ __('Custom domain') }}</h2>
                @if ($church->custom_domain)
                    <p class="small mb-2"><i class="bi bi-globe"></i> {{ $church->custom_domain }} — {{ $church->custom_domain_verified ? __('connected') : __('being set up') }}</p>
                @else
                    <p class="small text-ff-muted">{{ __('Use your own address like www.gracechurch.org for UGX :p/year.', ['p' => number_format(config('faithfellows.pricing.domain_price'))]) }}</p>
                    <x-ff.input name="custom_domain" :placeholder="'gracechurch.org'" :label="__('Domain')" />
                    <button class="btn btn-ff-outline w-100">{{ __('Request domain') }}</button>
                @endif
            </form>
        </div>
    </div>

    @foreach ($invoices as $invoice)
        @if ($invoice->isPayable())
            <x-ff.modal :id="'pay'.$invoice->id" :title="__('Pay invoice :n', ['n' => $invoice->number])">
                <form method="POST" action="{{ route('church-admin.billing.pay', [$church, $invoice]) }}" id="payForm{{ $invoice->id }}" x-data="givingForm({ amount: {{ (float) $invoice->total }}, method: 'mtn_momo' })">@csrf
                    <p class="fs-4 fw-bold text-ff-black">{{ ff_money($invoice->total) }}</p>
                    <input type="hidden" name="method" value="mtn_momo">
                    <p class="small text-ff-muted">{{ __('You pay with mobile money (MTN or Airtel). Enter the number to charge: a prompt appears on that phone, and you approve it with your PIN.') }}</p>
                    <x-ff.input name="phone" type="tel" :label="__('Mobile money number')" :value="$church->phone" :id="'phone'.$invoice->id" required />
                </form>
                <x-slot:footer><button form="payForm{{ $invoice->id }}" class="btn btn-ff-primary px-4">{{ __('Pay now') }}</button></x-slot:footer>
            </x-ff.modal>
        @endif
    @endforeach
</x-layouts.church-admin>
