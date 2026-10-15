<x-layouts.super-admin :title="__('Push test')">
    <x-ff.page-header :title="__('Push test')" :subtitle="__('Send a test notification to one member’s phone and see exactly what happened, for Android and iPhone.')" />

    <div class="row g-4">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('super-admin.push.send') }}" class="ff-card mb-4">
                @csrf
                <h2 class="h6 mb-2">{{ __('Send a test') }}</h2>
                <p class="small text-ff-muted">{{ __('Use the email of a member who is signed in on the phone. The phone must have opened the app, allowed notifications and signed in once.') }}</p>
                <div class="d-flex gap-2 align-items-start">
                    <div class="flex-grow-1"><x-ff.input name="email" type="email" :value="old('email', auth()->user()->email)" required :label="__('Member’s email')" /></div>
                    <button class="btn btn-ff-primary mt-4"><i class="bi bi-send"></i> {{ __('Send test') }}</button>
                </div>
            </form>

            @if ($results)
                <div class="ff-card mb-4">
                    <h2 class="h6 mb-3">{{ __('What the push service said') }}</h2>
                    <div class="d-grid gap-3">
                        @foreach ($results as $r)
                            <div class="border rounded-3 p-3">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="ff-badge {{ $r['status'] === 'ok' ? 'ff-badge-success' : 'ff-badge-danger' }}">{{ $r['status'] === 'ok' ? __('Accepted by Expo') : __('Refused') }}</span>
                                    <strong class="text-ff-black">{{ ucfirst($r['platform']) }}</strong>
                                    @if ($r['name'])<span class="small text-ff-muted">{{ $r['name'] }}</span>@endif
                                    @if ($r['app_version'])<span class="small text-ff-muted">· {{ __('app :v', ['v' => $r['app_version']]) }}</span>@endif
                                </div>
                                <div class="small-xs text-ff-muted mt-1">{{ $r['token'] }}</div>
                                @if ($r['status'] !== 'ok')
                                    <div class="small mt-2 text-danger">{{ $hints[$r['error']] ?? $r['message'] ?? __('Unknown problem.') }}</div>
                                @elseif ($receipts && $r['ticket'] && isset($receipts[$r['ticket']]))
                                    @php($rc = $receipts[$r['ticket']])
                                    <div class="small mt-2 {{ $rc['status'] === 'ok' ? 'text-success' : 'text-danger' }}">
                                        {{ $rc['status'] === 'ok' ? __('Delivered to Google/Apple: the notification should be on the phone now.') : ($hints[$rc['error']] ?? $rc['message'] ?? __('Delivery failed.')) }}
                                    </div>
                                @else
                                    <div class="small mt-2 text-ff-muted">{{ __('Expo accepted it. Press “Check delivery” in a few seconds to see if Google or Apple delivered it.') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if (! $receipts && collect($results)->whereNotNull('ticket')->isNotEmpty())
                        <form method="POST" action="{{ route('super-admin.push.receipts') }}" class="mt-3">
                            @csrf
                            @foreach (collect($results)->pluck('ticket')->filter() as $ticket)<input type="hidden" name="tickets[]" value="{{ $ticket }}">@endforeach
                            <input type="hidden" name="email" value="{{ old('email') }}">
                            <button class="btn btn-ff-ghost"><i class="bi bi-arrow-repeat"></i> {{ __('Check delivery') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="col-xl-5">
            <div class="ff-card mb-4">
                <h2 class="h6 mb-3">{{ __('Phones registered for notifications') }}</h2>
                @forelse ($devices as $d)
                    <div class="d-flex justify-content-between small py-1"><span>{{ ucfirst($d->platform ?: 'unknown') }}</span><strong>{{ number_format($d->n) }} {{ __('phones') }} · {{ number_format($d->people) }} {{ __('people') }}</strong></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No phone has registered yet. A phone registers when the member opens the app, allows notifications and signs in.') }}</p>
                @endforelse
            </div>
            @if ($recent->isNotEmpty())
                <div class="ff-card">
                    <h2 class="h6 mb-3">{{ __('Latest phones') }}</h2>
                    @foreach ($recent as $d)
                        <div class="small py-1 d-flex justify-content-between gap-2"><span class="text-truncate">{{ $d->user?->email ?? '—' }}</span><span class="text-ff-muted text-nowrap">{{ ucfirst($d->platform ?: '?') }} · {{ $d->last_used_at?->diffForHumans(short: true) }}</span></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.super-admin>
