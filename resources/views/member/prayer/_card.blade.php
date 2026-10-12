{{-- Prayer wall card. Expects $prayer (with church, user) and $prayed (bool). Optional $limit. --}}
<article @class(['ff-prayer-card', 'is-answered' => $prayer->status === 'answered'])>
    <div class="ff-feed-meta">
        @if ($prayer->is_anonymous)
            <span class="ff-avatar ff-avatar-sm" aria-hidden="true"><i class="bi bi-incognito"></i></span>
        @else
            <x-ff.avatar :user="$prayer->user" size="sm" />
        @endif
        <div class="min-w-0">
            <strong class="d-block text-truncate">{{ $prayer->author_name }}</strong>
            <div class="small-xs text-ff-muted text-truncate">{{ $prayer->church->name }} · {{ $prayer->created_at->diffForHumans() }}</div>
        </div>
        <span class="ff-feed-type d-flex gap-1">
            @if ($prayer->visibility === 'pastor')<span class="ff-badge ff-badge-dark" title="{{ __('Only pastors can see this') }}"><i class="bi bi-lock-fill"></i> {{ __('Pastors') }}</span>@endif
            @if ($prayer->status === 'answered')<span class="ff-badge ff-badge-success"><i class="bi bi-check2-circle"></i> {{ __('Answered') }}</span>@endif
            @if ($prayer->status === 'hidden')<x-ff.status status="hidden" />@endif
        </span>
    </div>

    @if ($prayer->title)<h3 class="h6 mb-0 text-ff-black">{{ $prayer->title }}</h3>@endif
    <blockquote style="white-space: pre-line">{{ Str::limit($prayer->body, $limit ?? 280) }}</blockquote>

    @if ($prayer->status === 'answered' && $prayer->answer_note)
        <div class="small p-2 rounded-3" style="background: rgba(30,158,90,.08)"><i class="bi bi-stars text-success"></i> {{ Str::limit($prayer->answer_note, 200) }}</div>
    @endif

    <div class="d-flex align-items-center gap-2 mt-auto pt-1">
        @if ($prayer->status !== 'hidden')
            <button type="button" class="btn btn-sm btn-ff-soft ff-pray-btn" :class="{ 'is-prayed': prayed }"
                x-data="prayButton({ url: @js(route('prayer.pray', $prayer)), prayed: @js((bool) $prayed), count: {{ (int) $prayer->prayed_count }}, guest: @js(! auth()->check()), loginUrl: @js(route('login')) })"
                @click="toggle()" :disabled="busy" :aria-pressed="prayed">
                <span aria-hidden="true">🙏</span>
                <span x-text="prayed ? @js(__('Prayed')) : @js(__('I prayed'))">{{ $prayed ? __('Prayed') : __('I prayed') }}</span>
                <span class="opacity-75" x-text="count">{{ $prayer->prayed_count }}</span>
            </button>
        @endif
        @if ($prayer->chain_enabled && $prayer->status === 'open')<span class="small-xs text-ff-muted" title="{{ __('Prayer chain') }}"><i class="bi bi-link-45deg"></i> {{ __('Chain') }}</span>@endif
        <a href="{{ route('prayer.show', $prayer) }}" class="small ms-auto">{{ __('Open') }} <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
