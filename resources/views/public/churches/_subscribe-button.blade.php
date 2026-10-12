@php($subscription = $sections['subscription'] ?? null)
@guest
    <a href="{{ route('churches.join', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Subscribe') }}</a>
@else
    @if ($subscription?->status === 'active')
        <div class="dropdown">
            <button class="btn btn-ff-soft dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-check2"></i> {{ __('Subscribed') }}</button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('notification-preferences.edit', ['church' => $church->id]) }}"><i class="bi bi-bell me-2"></i>{{ __('Notification settings') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('dashboard', ['church' => $church->slug]) }}"><i class="bi bi-house me-2"></i>{{ __('View in my feed') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('churches.unsubscribe', $church) }}" data-ff-confirm="{{ __('Unsubscribe from :church?', ['church' => $church->name]) }}">
                        @csrf @method('DELETE')
                        <button class="dropdown-item text-danger"><i class="bi bi-x-circle me-2"></i>{{ __('Unsubscribe') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    @elseif ($subscription?->status === 'pending')
        <form method="POST" action="{{ route('churches.unsubscribe', $church) }}">
            @csrf @method('DELETE')
            <button class="btn btn-ff-soft" title="{{ __('Cancel request') }}"><i class="bi bi-hourglass-split"></i> {{ __('Awaiting approval') }}</button>
        </form>
    @elseif ($subscription?->status !== 'blocked' && $church->isActive())
        @php($limit = (int) config('faithfellows.limits.subscriptions', 3))
        @php($atLimit = app(\App\Services\SubscriptionService::class)->activeCount(auth()->user()) >= $limit)
        @if ($atLimit)
            <a href="{{ route('my-churches.index') }}" class="btn btn-ff-soft text-nowrap" title="{{ __('You already follow :n churches — unsubscribe from one to follow another.', ['n' => $limit]) }}"><i class="bi bi-lock"></i> {{ __('Limit reached (:n/:n)', ['n' => $limit]) }}</a>
        @else
            <form method="POST" action="{{ route('churches.subscribe', $church) }}">
                @csrf
                <input type="hidden" name="source" value="{{ request('ref', 'church_page') }}">
                <button class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Subscribe') }}</button>
            </form>
        @endif
    @endif
@endguest
