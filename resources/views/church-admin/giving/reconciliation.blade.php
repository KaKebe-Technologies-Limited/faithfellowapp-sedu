<x-layouts.church-admin :church="$church" :title="__('Reconciliation')">
    <x-ff.page-header :title="__('Finance')" />
    @include('church-admin.giving._nav')

    <div class="row g-4">
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <form method="POST" action="{{ route('church-admin.reconciliation.import', $church) }}" enctype="multipart/form-data" class="ff-card">@csrf
                <h2 class="h6">{{ __('Import bank statement') }}</h2>
                <p class="small text-ff-muted">{{ __('CSV with columns: date, description, reference, amount. We match lines to gifts by FaithFellows reference, or by amount and date for pending bank transfers.') }}</p>
                <input type="file" name="statement" accept=".csv,text/csv" class="form-control mb-2 @error('statement') is-invalid @enderror" aria-label="{{ __('Statement file') }}">
                @error('statement')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-ff-primary w-100"><i class="bi bi-upload"></i> {{ __('Import & auto-match') }}</button>
            </form>
            <div class="ff-card ff-card-gray">
                <div class="d-flex justify-content-between small"><span>{{ __('Unmatched') }}</span><strong>{{ $counts['unmatched'] ?? 0 }}</strong></div>
                <div class="d-flex justify-content-between small"><span>{{ __('Matched') }}</span><strong>{{ $counts['matched'] ?? 0 }}</strong></div>
                <div class="d-flex justify-content-between small"><span>{{ __('Ignored') }}</span><strong>{{ $counts['ignored'] ?? 0 }}</strong></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="ff-pills mb-3">
                @foreach (['unmatched', 'matched', 'ignored'] as $s)
                    <a href="{{ route('church-admin.reconciliation.index', [$church, 'status' => $s]) }}" @class(['ff-pill', 'active' => request('status', 'unmatched') === $s])>{{ __(ucfirst($s)) }}</a>
                @endforeach
            </div>
            <div class="ff-table-wrap">
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Match') }}</th></tr></thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr>
                                    <td class="small text-nowrap">{{ $line->transaction_date->format('j M Y') }}</td>
                                    <td class="small">{{ $line->description }}<div class="small-xs text-ff-muted">{{ $line->reference }}</div></td>
                                    <td class="text-end fw-semibold">{{ ff_money($line->amount) }}</td>
                                    <td>
                                        @if ($line->status === 'matched')
                                            <span class="small text-success"><i class="bi bi-link-45deg"></i> {{ $line->donation?->receipt_number ?? $line->donation?->reference }}</span>
                                            <form method="POST" action="{{ route('church-admin.reconciliation.match', [$church, $line]) }}" class="d-inline">@csrf @method('PUT')<button name="action" value="unmatch" class="btn btn-sm btn-link p-0 ms-1">{{ __('undo') }}</button></form>
                                        @elseif ($line->status === 'unmatched')
                                            <form method="POST" action="{{ route('church-admin.reconciliation.match', [$church, $line]) }}" class="d-flex gap-1">@csrf @method('PUT')
                                                <select name="donation_id" class="form-select form-select-sm" aria-label="{{ __('Gift') }}">
                                                    @foreach ($pendingBank->filter(fn ($d) => abs($d->amount - $line->amount) < 1) as $d)<option value="{{ $d->id }}">{{ $d->display_name }} · {{ $d->reference }}</option>@endforeach
                                                    <option value="">{{ __('— no candidate —') }}</option>
                                                </select>
                                                <button class="btn btn-sm btn-ff-soft">{{ __('Match') }}</button>
                                                <button name="action" value="ignore" class="btn btn-sm btn-ff-ghost">{{ __('Ignore') }}</button>
                                            </form>
                                        @else
                                            <span class="small text-ff-muted">{{ __('Ignored') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ff.empty-state icon="bi-check2-all" :title="__('Nothing to reconcile')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($lines->hasPages())<div class="ff-table-footer">{{ $lines->links() }}</div>@endif
            </div>
        </div>
    </div>
</x-layouts.church-admin>
