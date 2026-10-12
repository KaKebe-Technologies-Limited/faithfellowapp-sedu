<x-layouts.member :title="__('Giving')">
    <x-ff.page-header :title="__('My giving')" :subtitle="__('Your gifts, receipts, pledges and recurring giving.')">
        @if ($churches->count() === 1)
            <a href="{{ route('give.create', $churches->first()) }}" class="btn btn-ff-primary"><i class="bi bi-heart"></i> {{ __('Give now') }}</a>
        @elseif ($churches->isNotEmpty())
            <div class="dropdown">
                <button class="btn btn-ff-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-heart"></i> {{ __('Give now') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach ($churches as $c)<li><a class="dropdown-item" href="{{ route('give.create', $c) }}">{{ $c->name }}</a></li>@endforeach
                </ul>
            </div>
        @endif
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><x-ff.stat-card :label="__('Given in :year', ['year' => now()->year])" :value="ff_money($yearTotal)" icon="bi-heart-fill" primary /></div>
        @foreach ($byChurch->take(2) as $row)
            <div class="col-md-4"><x-ff.stat-card :label="$row['church']->name" :value="ff_money($row['total'])" icon="bi-building" /></div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar"><h2 class="h6 mb-0">{{ __('History') }}</h2></div>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Church') }}</th><th>{{ __('For') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($donations as $d)
                                <tr>
                                    <td class="small text-nowrap">{{ ($d->paid_at ?? $d->created_at)->format('j M Y') }}</td>
                                    <td class="small">{{ $d->church->name }}</td>
                                    <td class="small">{{ $d->campaign?->title ?? $d->category_label }}</td>
                                    <td class="text-end fw-semibold text-ff-black text-nowrap">{{ ff_money($d->amount, $d->currency) }}</td>
                                    <td><x-ff.status :status="$d->status" /></td>
                                    <td class="text-end">
                                        @if (in_array($d->status, ['succeeded', 'refunded'], true) && $d->receipt_number)
                                            <a href="{{ route('giving.receipt', $d->reference) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Receipt') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                                        @elseif (in_array($d->status, ['pending', 'processing'], true))
                                            <a href="{{ route('give.status', $d->reference) }}" class="btn btn-sm btn-ff-soft">{{ __('Check') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ff.empty-state icon="bi-heart" :title="__('No gifts yet')" :message="__('When you give through FaithFellows, your receipts appear here.')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($donations->hasPages())<div class="ff-table-footer">{{ $donations->links() }}</div>@endif
            </div>
        </div>

        <aside class="col-xl-4 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Pledges')">
                @forelse ($pledges as $pledge)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small"><strong class="text-ff-black">{{ $pledge->campaign?->title ?? config('faithfellows.giving_categories.'.$pledge->category) }}</strong><x-ff.status :status="$pledge->status" /></div>
                        <div class="small text-ff-muted mb-1">{{ $pledge->church->name }} · {{ ff_money($pledge->paid_amount) }} / {{ ff_money($pledge->amount) }}</div>
                        <x-ff.progress :value="$pledge->percent()" size="sm" />
                        <div class="d-flex gap-2 mt-2">
                            <a href="{{ route('give.create', [$pledge->church, 'pledge' => $pledge->id, 'campaign' => $pledge->campaign?->slug]) }}" class="btn btn-sm btn-ff-primary">{{ __('Pay :amount', ['amount' => ff_money(min($pledge->installmentAmount(), $pledge->balance()))]) }}</a>
                            <form method="POST" action="{{ route('pledges.cancel', $pledge) }}" data-ff-confirm="{{ __('Cancel this pledge?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ff-ghost">{{ __('Cancel') }}</button></form>
                        </div>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No active pledges. Pledge towards a campaign and pay in installments.') }}</p>
                @endforelse
            </x-ff.card>

            <x-ff.card :title="__('Recurring gifts')">
                @forelse ($recurring as $gift)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom small">
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-ff-black">{{ ff_money($gift->amount) }} · {{ __(ucfirst($gift->frequency)) }}</div>
                            <div class="text-ff-muted">{{ $gift->church->name }} · {{ __('next :date', ['date' => $gift->next_run_on->format('j M')]) }} @if ($gift->status === 'paused')· <span class="text-warning">{{ __('paused') }}</span>@endif</div>
                        </div>
                        <form method="POST" action="{{ route('recurring.update', $gift) }}">@csrf @method('PUT')
                            <input type="hidden" name="status" value="{{ $gift->status === 'active' ? 'paused' : 'active' }}">
                            <button class="btn btn-sm btn-ff-ghost" aria-label="{{ $gift->status === 'active' ? __('Pause') : __('Resume') }}"><i class="bi {{ $gift->status === 'active' ? 'bi-pause' : 'bi-play' }}"></i></button>
                        </form>
                        <form method="POST" action="{{ route('recurring.update', $gift) }}" data-ff-confirm="{{ __('Stop this recurring gift?') }}">@csrf @method('PUT')
                            <input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Cancel') }}"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('Set up a weekly or monthly tithe when you give.') }}</p>
                @endforelse
            </x-ff.card>
        </aside>
    </div>
</x-layouts.member>
