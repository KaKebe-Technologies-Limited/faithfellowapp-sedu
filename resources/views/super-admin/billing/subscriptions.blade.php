<x-layouts.super-admin :title="__('Subscriptions')">
    <x-ff.page-header :title="__('Subscriptions')" :subtitle="__('Church plans, trials and billing status.')">
        <form method="POST" action="{{ route('super-admin.invoices.generate') }}">@csrf<button class="btn btn-ff-outline"><i class="bi bi-receipt"></i> {{ __('Generate due invoices') }}</button></form>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Monthly recurring revenue')" :value="ff_money($mrr)" icon="bi-graph-up-arrow" primary /></div>
        @foreach (['trial' => 'bi-gift', 'active' => 'bi-check-circle', 'past_due' => 'bi-exclamation-triangle'] as $s => $icon)
            <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__(Str::headline($s))" :value="$counts[$s] ?? 0" :icon="$icon" :href="route('super-admin.subscriptions.index', ['billing' => $s])" /></div>
        @endforeach
    </div>

    <div class="ff-table-wrap">
        <form method="GET" class="ff-table-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 240px" placeholder="{{ __('Search churches…') }}" aria-label="{{ __('Search') }}">
            <select name="billing" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Billing status') }}">
                <option value="">{{ __('Any status') }}</option>
                @foreach (['trial', 'active', 'past_due', 'suspended', 'cancelled'] as $s)<option value="{{ $s }}" @selected(request('billing') === $s)>{{ Str::headline($s) }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-ff-primary">{{ __('Filter') }}</button>
        </form>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Church') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Status') }}</th><th>{{ __('Trial ends') }}</th><th>{{ __('Paid until') }}</th><th class="text-end">{{ __('Outstanding') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($churches as $church)
                        <tr>
                            <td><a href="{{ route('super-admin.churches.show', $church) }}" class="fw-semibold text-ff-black">{{ $church->name }}</a></td>
                            <td class="small">{{ $church->plan?->name ?? '—' }}@if ($church->domain_addon)<span class="ff-badge ms-1">{{ __('domain') }}</span>@endif</td>
                            <td><x-ff.status :status="$church->billing_status" /></td>
                            <td class="small">{{ $church->trial_ends_at?->format('j M Y') ?? '—' }}</td>
                            <td class="small">{{ $church->paid_until?->format('j M Y') ?? '—' }}</td>
                            <td class="text-end">{{ $church->outstanding ? ff_money($church->outstanding) : '—' }}</td>
                            <td class="text-end"><button class="btn btn-sm btn-ff-soft" data-bs-toggle="modal" data-bs-target="#sub{{ $church->id }}">{{ __('Edit') }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($churches->hasPages())<div class="ff-table-footer">{{ $churches->links() }}</div>@endif
    </div>

    @foreach ($churches as $church)
        <x-ff.modal :id="'sub'.$church->id" :title="$church->name">
            <form method="POST" action="{{ route('super-admin.subscriptions.update', $church) }}" id="subForm{{ $church->id }}">@csrf @method('PUT')
                <x-ff.select name="plan_id" :label="__('Plan')" :options="$plans->pluck('name', 'id')" :value="$church->plan_id" :id="'plan'.$church->id" />
                <x-ff.select name="billing_status" :label="__('Billing status')" :options="collect(['trial', 'active', 'past_due', 'suspended', 'cancelled'])->mapWithKeys(fn ($s) => [$s => Str::headline($s)])" :value="$church->billing_status" :id="'bs'.$church->id" />
                <div class="row">
                    <div class="col-6"><x-ff.input name="trial_ends_at" type="date" :label="__('Trial ends')" :value="$church->trial_ends_at?->format('Y-m-d')" :id="'te'.$church->id" /></div>
                    <div class="col-6"><x-ff.input name="paid_until" type="date" :label="__('Paid until')" :value="$church->paid_until?->format('Y-m-d')" :id="'pu'.$church->id" /></div>
                </div>
                <x-ff.checkbox name="domain_addon" :label="__('Custom domain add-on')" :checked="$church->domain_addon" :id="'da'.$church->id" switch />
            </form>
            <x-slot:footer><button form="subForm{{ $church->id }}" class="btn btn-ff-primary">{{ __('Save') }}</button></x-slot:footer>
        </x-ff.modal>
    @endforeach
</x-layouts.super-admin>
