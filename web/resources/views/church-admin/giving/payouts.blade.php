<x-layouts.church-admin :church="$church" :title="__('Payouts')">
    <x-ff.page-header :title="__('Finance')" />
    @include('church-admin.giving._nav')

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="ff-card ff-card-mist">
                <h2 class="h6">{{ __('Unsettled online gifts') }}</h2>
                <div class="fs-3 fw-bold text-ff-black">{{ ff_money($unsettledTotal) }}</div>
                <p class="small">{{ trans_choice(':count gift|:count gifts', $unsettledCount) }} @if ($oldest)· {{ __('since :d', ['d' => \Illuminate\Support\Carbon::parse($oldest)->format('j M')]) }}@endif</p>
                @if (auth()->user()->canInChurch($church, 'giving.manage') && $unsettledCount)
                    <form method="POST" action="{{ route('church-admin.payouts.store', $church) }}">@csrf
                        <x-ff.input name="until" type="date" :label="__('Settle gifts up to')" :value="today()->toDateString()" />
                        <x-ff.select name="method" :label="__('Paid to')" :options="['bank' => __('Church bank account'), 'mobile_money' => __('Church mobile money')]" value="bank" />
                        <button class="btn btn-ff-primary w-100">{{ __('Create payout') }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="col-lg-8">
            <div class="ff-table-wrap">
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Period') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Gifts') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payouts as $payout)
                                <tr>
                                    <td class="small">{{ $payout->period_start->format('j M') }} – {{ $payout->period_end->format('j M Y') }}</td>
                                    <td class="text-end fw-semibold">{{ ff_money($payout->amount) }}</td>
                                    <td>{{ $payout->donations_count }}</td>
                                    <td><x-ff.status :status="$payout->status" /></td>
                                    <td class="small">{{ $payout->reference ?: '—' }}</td>
                                    <td class="text-end">
                                        @if (auth()->user()->canInChurch($church, 'giving.manage') && $payout->status !== 'paid')
                                            <form method="POST" action="{{ route('church-admin.payouts.update', [$church, $payout]) }}" class="d-flex gap-1 justify-content-end">@csrf @method('PUT')
                                                <input name="reference" class="form-control form-control-sm" style="width: 120px" placeholder="{{ __('Bank ref') }}" aria-label="{{ __('Reference') }}">
                                                <button name="status" value="paid" class="btn btn-sm btn-ff-soft">{{ __('Mark paid') }}</button>
                                                <button name="status" value="failed" class="btn btn-sm btn-ff-ghost text-danger">{{ __('Failed') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ff.empty-state icon="bi-bank" :title="__('No payouts yet')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($payouts->hasPages())<div class="ff-table-footer">{{ $payouts->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-layouts.church-admin>
