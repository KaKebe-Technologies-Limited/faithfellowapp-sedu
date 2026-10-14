{{-- Campaign card. Expects $campaign (church + media loaded). --}}
<article class="ff-card ff-card-flush ff-card-hover ff-campaign-card h-100 d-flex flex-column">
    @if ($campaign->picture_url)
        <img src="{{ $campaign->picture_url }}" alt="" class="ff-cover" loading="lazy">
    @else
        <div class="ff-cover-placeholder"><i class="bi bi-flag"></i></div>
    @endif
    <div class="p-4 d-flex flex-column flex-grow-1">
        <div class="d-flex gap-2 mb-2"><span class="ff-tag">{{ $campaign->type_label }}</span>@if ($campaign->goal_reached_at)<span class="ff-badge ff-badge-success">🎉 {{ __('Goal reached') }}</span>@endif</div>
        <h3 class="h6 mb-1"><a href="{{ route('campaigns.show', $campaign) }}" class="text-ff-black stretched-link">{{ $campaign->title }}</a></h3>
        <div class="small text-ff-muted mb-3">{{ $campaign->church->name }}</div>
        <div class="mt-auto">
            <x-ff.progress :value="$campaign->percent()" class="mb-2" />
            <div class="ff-campaign-stats">
                <div><strong>{{ ff_short_number($campaign->raised_amount) }}</strong>{{ __('raised of :goal', ['goal' => ff_short_number($campaign->goal_amount)]) }}</div>
                <div class="text-end"><strong>{{ round($campaign->percent()) }}%</strong>@if ($campaign->daysLeft() !== null){{ trans_choice(':count day left|:count days left', $campaign->daysLeft()) }}@else{{ trans_choice(':count giver|:count givers', $campaign->givers_count) }}@endif</div>
            </div>
        </div>
    </div>
</article>
