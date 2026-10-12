<x-layouts.super-admin :title="__('Invoices')">
    <x-ff.page-header :title="__('Invoices')" :subtitle="__('Church subscription invoices issued by Kakebe Technologies.')">
        <button class="btn btn-ff-primary" data-bs-toggle="modal" data-bs-target="#newInvoice"><i class="bi bi-plus-lg"></i> {{ __('New invoice') }}</button>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        @foreach (['paid' => 'bi-check-circle', 'unpaid' => 'bi-hourglass', 'overdue' => 'bi-exclamation-triangle'] as $s => $icon)
            <div class="col-md-4"><x-ff.stat-card :label="__(ucfirst($s))" :value="ff_money($totals[$s]->t ?? 0)" :icon="$icon" :href="route('super-admin.invoices.index', ['status' => $s])">{{ trans_choice(':count invoice|:count invoices', $totals[$s]->c ?? 0) }}</x-ff.stat-card></div>
        @endforeach
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Church') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="fw-semibold text-ff-black">{{ $invoice->number }}</td>
                            <td class="small">{{ $invoice->church?->name }}</td>
                            <td class="small">{{ $invoice->period_start->format('j M') }} – {{ $invoice->period_end->format('j M Y') }}</td>
                            <td class="text-end">{{ ff_money($invoice->total) }}</td>
                            <td class="small">{{ $invoice->due_date->format('j M Y') }}</td>
                            <td><x-ff.status :status="$invoice->status" />@if ($invoice->paid_at)<div class="small-xs text-ff-muted">{{ $invoice->method }} · {{ $invoice->paid_at->format('j M') }}</div>@endif</td>
                            <td class="text-end text-nowrap">
                                @if ($invoice->isPayable())
                                    <form method="POST" action="{{ route('super-admin.invoices.paid', $invoice) }}" class="d-inline-flex gap-1">@csrf
                                        <select name="method" class="form-select form-select-sm" aria-label="{{ __('Method') }}"><option value="bank">{{ __('Bank') }}</option><option value="cash">{{ __('Cash') }}</option><option value="mtn_momo">MTN</option><option value="airtel_money">Airtel</option></select>
                                        <button class="btn btn-sm btn-ff-soft">{{ __('Mark paid') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('super-admin.invoices.void', $invoice) }}" class="d-inline" data-ff-confirm="{{ __('Void this invoice?') }}">@csrf<button class="btn btn-sm btn-ff-ghost">{{ __('Void') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ff.empty-state icon="bi-receipt" :title="__('No invoices')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())<div class="ff-table-footer">{{ $invoices->links() }}</div>@endif
    </div>

    <x-ff.modal id="newInvoice" :title="__('Create invoice')">
        <form method="POST" action="{{ route('super-admin.invoices.store') }}" id="newInvoiceForm">@csrf
            <x-ff.select name="church_id" :label="__('Church')" :options="$churches" />
            <x-ff.input name="period_start" type="date" :label="__('Period starts')" :value="today()->toDateString()" />
        </form>
        <x-slot:footer><button form="newInvoiceForm" class="btn btn-ff-primary">{{ __('Create') }}</button></x-slot:footer>
    </x-ff.modal>
</x-layouts.super-admin>
