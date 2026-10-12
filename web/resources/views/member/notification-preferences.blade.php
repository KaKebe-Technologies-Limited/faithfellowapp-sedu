<x-layouts.member :title="__('Notification preferences')">
    <x-ff.page-header :title="__('Notification preferences')" :subtitle="__('Choose what each church can send you, and how.')"
        :breadcrumbs="[__('Profile') => route('profile.edit'), __('Notifications') => null]" />

    <div class="row g-4">
        <div class="col-lg-3">
            <nav class="ff-card ff-card-sm d-grid gap-1" aria-label="{{ __('Choose church') }}">
                <a href="{{ route('notification-preferences.edit', ['church' => 'global']) }}" @class(['ff-nav-link mb-0', 'active' => $selected === 'global'])><i class="bi bi-globe"></i> {{ __('Default for all churches') }}</a>
                @foreach ($churches as $church)
                    <a href="{{ route('notification-preferences.edit', ['church' => $church->id]) }}" @class(['ff-nav-link mb-0', 'active' => (string) $selected === (string) $church->id])>
                        <x-ff.church-avatar :church="$church" size="xs" /> <span class="text-truncate">{{ $church->name }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="col-lg-9">
            @php
                $scope = array_key_exists($selected, $matrix) ? $selected : 'global';
                $scopeName = $scope === 'global' ? __('all churches (default)') : $churches->firstWhere('id', (int) $scope)?->name;
            @endphp
            <form method="POST" action="{{ route('notification-preferences.update') }}" class="ff-table-wrap">
                @csrf @method('PUT')
                <input type="hidden" name="scope" value="{{ $scope }}">
                <div class="ff-table-toolbar">
                    <div>
                        <h2 class="h6 mb-0">{{ __('Alerts from :scope', ['scope' => $scopeName]) }}</h2>
                        <span class="small text-ff-muted">{{ __('Untick every channel to turn a category off.') }}</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table ff-table ff-matrix">
                        <thead>
                            <tr>
                                <th>{{ __('Category') }}</th>
                                @foreach ($channels as $channelKey => $channelLabel)<th>{{ __($channelLabel) }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $catKey => $catLabel)
                                <tr>
                                    <td class="fw-semibold text-ff-black">{{ __($catLabel) }}</td>
                                    @foreach ($channels as $channelKey => $channelLabel)
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="prefs[{{ $scope }}][{{ $catKey }}][]" value="{{ $channelKey }}"
                                                   aria-label="{{ $catLabel }} — {{ $channelLabel }}" @checked(in_array($channelKey, $matrix[$scope][$catKey] ?? [], true))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ff-table-footer d-flex flex-wrap gap-3 align-items-center">
                    <button class="btn btn-ff-primary px-4">{{ __('Save') }}</button>
                    <span class="small text-ff-muted">
                        {{ __('Quiet hours, daily digest and language are in') }} <a href="{{ route('profile.edit', ['tab' => 'preferences']) }}">{{ __('Profile → Preferences') }}</a>.
                        {{ __('If none of your chosen channels can reach you (e.g. no push on this device) we fall back to email — and to SMS only for urgent messages.') }}
                    </span>
                </div>
            </form>

            <div class="ff-card ff-card-mist mt-4 d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="flex-grow-1">
                    <h2 class="h6 mb-1"><i class="bi bi-phone-vibrate"></i> {{ __('Push notifications on this device') }}</h2>
                    <p class="small mb-0">{{ __('Get live-service alerts instantly, even when FaithFellows is closed.') }}</p>
                </div>
                <button type="button" class="btn btn-ff-primary" data-ff-push-subscribe data-vapid="{{ config('services.webpush.public_key') }}" data-url="{{ Route::has('push-subscriptions.store') ? route('push-subscriptions.store') : '' }}">{{ __('Enable push') }}</button>
            </div>
        </div>
    </div>
</x-layouts.member>
