<x-layouts.church-admin :church="$church" :title="__('Campaigns')">
    <x-ff.page-header :title="__('Campaigns')" :subtitle="__('Fundraise for buildings, missions, welfare and more — with live progress and milestones.')">
        <a href="{{ route('church-admin.campaigns.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New campaign') }}</a>
    </x-ff.page-header>

    @if ($campaigns->isEmpty())
        <x-ff.empty-state icon="bi-flag" :title="__('No campaigns yet')" :message="__('Launch your first campaign — members can give, pledge and follow progress.')">
            <a href="{{ route('church-admin.campaigns.create', $church) }}" class="btn btn-ff-primary">{{ __('Create a campaign') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-4">
            @foreach ($campaigns as $campaign)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('church-admin.campaigns.show', [$church, $campaign]) }}" class="ff-card ff-card-flush ff-card-hover ff-card-link h-100 d-flex flex-column">
                        @if ($campaign->cover_url)<img src="{{ $campaign->cover_url }}" alt="" class="ff-cover">@else<div class="ff-cover-placeholder"><i class="bi bi-flag"></i></div>@endif
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex gap-2 mb-2"><x-ff.status :status="$campaign->status" /><span class="ff-tag">{{ $campaign->type_label }}</span></div>
                            <div class="fw-bold text-ff-black mb-2">{{ $campaign->title }}</div>
                            <div class="mt-auto">
                                <x-ff.progress :value="$campaign->percent()" class="mb-2" />
                                <div class="ff-campaign-stats">
                                    <div><strong>{{ ff_money($campaign->raised_amount) }}</strong>{{ __('of :g', ['g' => ff_short_number($campaign->goal_amount)]) }}</div>
                                    <div class="text-end"><strong>{{ $campaign->givers_count }}</strong>{{ __('givers') }} · {{ $campaign->pledges_count }} {{ __('pledges') }}</div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif
</x-layouts.church-admin>
