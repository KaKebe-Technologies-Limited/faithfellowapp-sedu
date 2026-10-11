<x-layouts.member :title="__('My churches')">
    <x-ff.page-header :title="__('My churches')" :subtitle="__('One account, many churches. Follow as many as you like.')">
        <a href="{{ route('churches.index') }}" class="btn btn-ff-primary"><i class="bi bi-compass"></i> {{ __('Discover churches') }}</a>
    </x-ff.page-header>

    @if ($subscriptions->isEmpty())
        <x-ff.empty-state icon="bi-bookmark-heart" :title="__('You are not following any churches yet')" :message="__('Find your home church and subscribe to get sermons, live services and updates.')">
            <a href="{{ route('churches.index') }}" class="btn btn-ff-primary">{{ __('Find a church') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-4">
            @foreach ($subscriptions as $subscription)
                @php($church = $subscription->church)
                <div class="col-md-6 col-xl-4">
                    <div class="ff-card h-100 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <x-ff.church-avatar :church="$church" size="lg" />
                            <div class="min-w-0">
                                <a href="{{ route('churches.show', $church) }}" class="fw-bold text-ff-black d-block text-truncate">{{ $church->name }}</a>
                                <div class="small text-ff-muted">{{ $church->location }}</div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <x-ff.status :status="$subscription->status">{{ $subscription->status === 'pending' ? __('Awaiting approval') : __('Subscribed') }}</x-ff.status>
                            @if ($muted[$church->id] ?? false)<span class="ff-badge"><i class="bi bi-bell-slash"></i> {{ __('Muted') }}</span>@endif
                            <span class="ff-badge">{{ __('Since :date', ['date' => $subscription->subscribed_at?->format('M Y')]) }}</span>
                        </div>
                        @if ($next = $church->serviceTimes->first())
                            <p class="small text-ff-muted mb-3"><i class="bi bi-clock"></i> {{ $next->name }} · {{ $next->day_name }} {{ $next->time_range }}</p>
                        @endif
                        <div class="mt-auto d-flex flex-wrap gap-2">
                            <a href="{{ route('dashboard', ['church' => $church->slug]) }}" class="btn btn-sm btn-ff-soft">{{ __('Feed') }}</a>
                            <a href="{{ route('notification-preferences.edit', ['church' => $church->id]) }}" class="btn btn-sm btn-ff-ghost"><i class="bi bi-sliders"></i> {{ __('Alerts') }}</a>
                            @if ($subscription->status === 'active')
                                <form method="POST" action="{{ route('churches.mute', $church) }}">@csrf @method('PUT')
                                    <input type="hidden" name="muted" value="{{ ($muted[$church->id] ?? false) ? 0 : 1 }}">
                                    <button class="btn btn-sm btn-ff-ghost" aria-label="{{ ($muted[$church->id] ?? false) ? __('Unmute') : __('Mute') }}"><i class="bi {{ ($muted[$church->id] ?? false) ? 'bi-bell' : 'bi-bell-slash' }}"></i></button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('churches.unsubscribe', $church) }}" class="ms-auto" data-ff-confirm="{{ __('Unsubscribe from :church?', ['church' => $church->name]) }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-ff-ghost text-danger">{{ __('Leave') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.member>
