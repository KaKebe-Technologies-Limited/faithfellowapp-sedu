<x-layouts.church-admin :church="$church" :title="$campaign->title">
    <x-ff.page-header :title="$campaign->title" :breadcrumbs="[__('Campaigns') => route('church-admin.campaigns.index', $church), $campaign->title => null]">
        <a href="{{ route('campaigns.show', $campaign) }}" target="_blank" class="btn btn-ff-ghost"><i class="bi bi-eye"></i> {{ __('Public page') }}</a>
        <a href="{{ route('campaigns.poster', $campaign) }}" target="_blank" class="btn btn-ff-ghost"><i class="bi bi-qr-code"></i> {{ __('QR poster') }}</a>
        <div class="dropdown">
            <button class="btn btn-ff-outline dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> {{ __('Givers') }}</button>
            <ul class="dropdown-menu"><li><a class="dropdown-item" href="{{ route('church-admin.campaigns.export', [$church, $campaign]) }}">CSV</a></li><li><a class="dropdown-item" href="{{ route('church-admin.campaigns.export', [$church, $campaign, 'format' => 'pdf']) }}">PDF</a></li></ul>
        </div>
        <a href="{{ route('church-admin.campaigns.edit', [$church, $campaign]) }}" class="btn btn-ff-primary">{{ __('Edit') }}</a>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Raised')" :value="ff_money($campaign->raised_amount)" icon="bi-cash-coin" primary>{{ round($campaign->percent()) }}% {{ __('of goal') }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Givers')" :value="number_format($campaign->givers_count)" icon="bi-people" /></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Pledged')" :value="ff_money($campaign->pledged_amount)" icon="bi-hand-thumbs-up">{{ trans_choice(':count pledge|:count pledges', $pledges->count()) }}</x-ff.stat-card></div>
        <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Remaining')" :value="ff_money($campaign->remaining())" icon="bi-flag">@if ($campaign->daysLeft() !== null){{ trans_choice(':count day left|:count days left', $campaign->daysLeft()) }}@endif</x-ff.stat-card></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.progress :value="$campaign->percent()" size="lg" class="mb-3" />
                <div class="row g-3">
                    @foreach ($campaign->milestones as $m)
                        <div class="col-6 col-md-3"><div @class(['ff-milestone', 'is-reached' => $m->reached_at])><span class="ff-milestone-dot">{{ $m->reached_at ? '✓' : $m->percent.'%' }}</span><span class="small">{{ $m->reached_at ? $m->reached_at->format('j M') : __('Not yet') }}</span></div></div>
                    @endforeach
                </div>
            </div>

            <x-ff.card :title="__('Gifts')" flush>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <tbody>
                            @forelse ($donations as $d)
                                <tr>
                                    <td class="small">{{ ($d->paid_at ?? $d->created_at)->format('j M Y') }}</td>
                                    <td class="small fw-semibold text-ff-black">{{ $d->display_name }}</td>
                                    <td class="small">{{ $d->method_label }}</td>
                                    <td class="text-end fw-semibold">{{ ff_money($d->amount) }}</td>
                                    <td><x-ff.status :status="$d->status" /></td>
                                </tr>
                            @empty
                                <tr><td><x-ff.empty-state icon="bi-heart" :title="__('No gifts yet — share the campaign!')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($donations->hasPages())<div class="ff-table-footer">{{ $donations->links() }}</div>@endif
            </x-ff.card>

            <form method="POST" action="{{ route('church-admin.campaigns.updates.store', [$church, $campaign]) }}" enctype="multipart/form-data" class="ff-card">@csrf
                <h2 class="h6">{{ __('Post an update') }}</h2>
                <x-ff.input name="title" :label="__('Headline')" required :placeholder="__('Roofing has started!')" />
                <x-ff.textarea name="body" :label="__('Details')" rows="3" />
                <div class="row align-items-end">
                    <div class="col-md-7"><label class="form-label" for="photos">{{ __('Photos') }}</label><input type="file" id="photos" name="photos[]" accept="image/*" multiple class="form-control"></div>
                    <div class="col-md-5"><x-ff.checkbox name="is_testimony" :label="__('This is a testimony')" /></div>
                </div>
                <button class="btn btn-ff-primary mt-3">{{ __('Post update') }}</button>
            </form>
        </div>
        <div class="col-xl-4 d-grid gap-4 align-content-start">
            @if ($campaign->teams->isNotEmpty())
                <x-ff.card :title="__('Teams')">
                    @foreach ($campaign->teams->sortByDesc('raised_amount') as $team)
                        <div class="d-flex justify-content-between small py-1"><strong style="color: {{ $team->color }}">{{ $team->name }}</strong><span>{{ ff_money($team->raised_amount) }}</span></div>
                    @endforeach
                </x-ff.card>
            @endif
            <x-ff.card :title="__('By method')">
                @forelse ($byMethod as $method => $total)
                    <div class="d-flex justify-content-between small py-1"><span>{{ config('faithfellows.payment_methods.'.$method, $method) }}</span><strong>{{ ff_money($total) }}</strong></div>
                @empty
                    <p class="small text-ff-muted mb-0">—</p>
                @endforelse
            </x-ff.card>
            <x-ff.card :title="__('Pledges')">
                @forelse ($pledges as $p)
                    <div class="small mb-2"><div class="d-flex justify-content-between"><span>{{ $p->is_anonymous ? __('Anonymous') : $p->user?->name }}</span><span>{{ ff_short_number($p->paid_amount) }}/{{ ff_short_number($p->amount) }}</span></div><x-ff.progress :value="$p->percent()" size="sm" /></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No pledges yet.') }}</p>
                @endforelse
            </x-ff.card>
            @if ($leaderboard->isNotEmpty())
                <x-ff.card :title="__('Top givers (public)')">
                    @foreach ($leaderboard as $i => $row)
                        <div class="ff-leaderboard-row"><span class="ff-leaderboard-rank">{{ $i + 1 }}</span><span class="flex-grow-1 small">{{ $row->name }}</span><span class="small">{{ ff_short_number($row->total) }}</span></div>
                    @endforeach
                </x-ff.card>
            @endif
            <form method="POST" action="{{ route('church-admin.campaigns.destroy', [$church, $campaign]) }}" data-ff-confirm="{{ __('Delete this campaign?') }}">@csrf @method('DELETE')
                <button class="btn btn-ff-ghost text-danger w-100">{{ __('Delete campaign') }}</button>
            </form>
        </div>
    </div>
</x-layouts.church-admin>
