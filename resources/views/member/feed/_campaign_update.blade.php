@php($campaign = $campaign_update->campaign)
<article class="ff-card ff-feed-item">
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$campaign->church" size="sm" />
        <div class="min-w-0">
            <a href="{{ route('churches.show', $campaign->church) }}" class="fw-semibold text-ff-black">{{ $campaign->church->name }}</a>
            <div class="small-xs text-ff-muted">{{ $campaign_update->published_at->diffForHumans() }}</div>
        </div>
        <span class="ff-feed-type ff-tag"><i class="bi bi-flag"></i> {{ $campaign_update->is_testimony ? __('Testimony') : __('Campaign') }}</span>
    </div>
    <div class="fw-bold text-ff-black">{{ $campaign_update->title }}</div>
    @if ($campaign_update->body)<div class="small">{!! Str::limit(strip_tags($campaign_update->body), 280) !!}</div>@endif
    @if ($campaign_update->media->isNotEmpty())
        <img src="{{ $campaign_update->media->first()->getUrl() }}" alt="" class="ff-cover rounded-3" loading="lazy">
    @endif
    <a href="{{ route('campaigns.show', $campaign) }}" class="ff-card ff-card-gray ff-card-sm ff-card-link">
        <div class="d-flex justify-content-between small mb-1"><strong class="text-ff-black">{{ $campaign->title }}</strong><span>{{ round($campaign->percent()) }}%</span></div>
        <x-ff.progress :value="$campaign->percent()" size="sm" />
    </a>
</article>
