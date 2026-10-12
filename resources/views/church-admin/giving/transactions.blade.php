<x-layouts.church-admin :church="$church" :title="__('Transactions')">
    <x-ff.page-header :title="__('Finance')">
        <a href="{{ route('church-admin.giving.export', array_merge(['church' => $church], request()->query())) }}" class="btn btn-ff-ghost"><i class="bi bi-filetype-csv"></i> CSV</a>
        <a href="{{ route('church-admin.giving.export', array_merge(['church' => $church, 'format' => 'pdf'], request()->query())) }}" class="btn btn-ff-ghost"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </x-ff.page-header>
    @include('church-admin.giving._nav')

    <div class="ff-table-wrap">
        <form method="GET" class="ff-table-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 200px" placeholder="{{ __('Name, phone, reference…') }}" aria-label="{{ __('Search') }}">
            <select name="status" class="form-select form-select-sm" style="max-width: 140px" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Any status') }}</option>
                @foreach (['succeeded', 'pending', 'processing', 'failed', 'refunded', 'disputed'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
            </select>
            <select name="category" class="form-select form-select-sm" style="max-width: 150px" aria-label="{{ __('Category') }}">
                <option value="">{{ __('Any category') }}</option>
                @foreach (config('faithfellows.giving_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="method" class="form-select form-select-sm" style="max-width: 150px" aria-label="{{ __('Method') }}">
                <option value="">{{ __('Any method') }}</option>
                @foreach (config('faithfellows.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ __($l) }}</option>@endforeach
            </select>
            @if ($campaigns->isNotEmpty())
                <select name="campaign" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Campaign') }}">
                    <option value="">{{ __('Any campaign') }}</option>
                    @foreach ($campaigns as $id => $t)<option value="{{ $id }}" @selected((string) request('campaign') === (string) $id)>{{ $t }}</option>@endforeach
                </select>
            @endif
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="max-width: 150px" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="max-width: 150px" aria-label="{{ __('To') }}">
            <button class="btn btn-sm btn-ff-primary">{{ __('Filter') }}</button>
            <span class="ms-auto small">{{ __('Total received:') }} <strong class="text-ff-black">{{ ff_money($total) }}</strong></span>
        </form>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Giver') }}</th><th>{{ __('For') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($donations as $d)
                        <tr>
                            <td class="small text-nowrap">{{ ($d->paid_at ?? $d->created_at)->format('j M Y H:i') }}</td>
                            <td class="small"><span class="fw-semibold text-ff-black">{{ $d->display_name }}</span>@if (! $d->is_anonymous && $d->phone)<div class="small-xs text-ff-muted">{{ $d->phone }}</div>@endif</td>
                            <td class="small">{{ $d->campaign?->title ?? $d->category_label }}@if ($d->branch)<div class="small-xs text-ff-muted">{{ $d->branch->name }}</div>@endif</td>
                            <td class="small">{{ $d->method_label }}@if ($d->recorder)<div class="small-xs text-ff-muted">{{ __('by :n', ['n' => $d->recorder->first_name]) }}</div>@endif</td>
                            <td class="text-end fw-semibold text-nowrap">{{ ff_money($d->amount, $d->currency) }}</td>
                            <td><x-ff.status :status="$d->status" />@if ($d->failure_reason)<div class="small-xs text-danger">{{ Str::limit($d->failure_reason, 40) }}</div>@endif</td>
                            <td class="small-xs text-ff-muted">{{ $d->receipt_number ?? $d->reference }}<div>{{ Str::limit($d->provider_ref, 18) }}</div></td>
                            <td class="text-end text-nowrap">
                                @can('reconcile', $d)
                                    @if ($d->method === 'bank' && in_array($d->status, ['pending', 'processing'], true))
                                        <form method="POST" action="{{ route('church-admin.giving.confirm', [$church, $d]) }}" class="d-inline" data-ff-confirm="{{ __('Confirm this transfer reached the church account?') }}">@csrf
                                            <button class="btn btn-sm btn-ff-soft">{{ __('Confirm') }}</button>
                                        </form>
                                    @endif
                                @endcan
                                @can('refund', $d)
                                    @if ($d->status === 'succeeded')
                                        <form method="POST" action="{{ route('church-admin.giving.refund', [$church, $d]) }}" class="d-inline" x-data @submit="if (!($el.querySelector('[name=reason]').value = prompt('{{ __('Reason for refund?') }}'))) $event.preventDefault()">@csrf
                                            <input type="hidden" name="reason">
                                            <button class="btn btn-sm btn-ff-ghost text-danger">{{ __('Refund') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ff.empty-state icon="bi-search" :title="__('No transactions match')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($donations->hasPages())<div class="ff-table-footer">{{ $donations->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
