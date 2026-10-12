<x-layouts.member :title="__('Notifications')">
    <x-ff.page-header :title="__('Notifications')">
        <a href="{{ route('notification-preferences.edit') }}" class="btn btn-ff-ghost"><i class="bi bi-sliders"></i> {{ __('Preferences') }}</a>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            <button class="btn btn-ff-outline"><i class="bi bi-check2-all"></i> {{ __('Mark all as read') }}</button>
        </form>
    </x-ff.page-header>

    <div class="ff-pills mb-3">
        <a href="{{ route('notifications.index') }}" @class(['ff-pill', 'active' => ! request('filter') && ! request('category')])>{{ __('All') }}</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" @class(['ff-pill', 'active' => request('filter') === 'unread'])>{{ __('Unread') }}</a>
        @foreach (config('faithfellows.notification_categories') as $key => $label)
            <a href="{{ route('notifications.index', ['category' => $key]) }}" @class(['ff-pill', 'active' => request('category') === $key])>{{ __($label) }}</a>
        @endforeach
    </div>

    <div class="ff-card ff-card-sm" style="max-width: 860px">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            <div class="d-flex align-items-start gap-2">
                <a href="{{ route('notifications.open', $notification->id) }}" @class(['ff-notification-item flex-grow-1', 'is-unread' => ! $notification->read_at])>
                    @if ($notification->church_id && isset($churches[$notification->church_id]))
                        <x-ff.church-avatar :church="$churches[$notification->church_id]" size="sm" />
                    @else
                        <span class="ff-avatar ff-avatar-sm" style="background: var(--ff-primary-mist); color: var(--ff-primary)"><i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i></span>
                    @endif
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold text-ff-black">{{ $data['title'] ?? __('Notification') }}</span>
                        @if (! empty($data['body']))<span class="d-block small text-ff-muted">{{ Str::limit($data['body'], 160) }}</span>@endif
                        <span class="d-block small-xs text-ff-muted mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                    @unless ($notification->read_at)<span class="ff-notification-dot" aria-label="{{ __('Unread') }}"></span>@endunless
                </a>
                <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="pt-2">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-ff-ghost btn-icon" aria-label="{{ __('Dismiss') }}"><i class="bi bi-x"></i></button>
                </form>
            </div>
        @empty
            <x-ff.empty-state icon="bi-bell" :title="__('No notifications')" :message="__('Live alerts, new sermons, receipts and more will show up here.')" class="border-0" />
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.member>
