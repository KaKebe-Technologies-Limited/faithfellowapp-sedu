<x-layouts.super-admin :title="__('Push test')">
    <x-ff.page-header :title="__('Push test')" :subtitle="__('Send a test notification to one member’s phone and see exactly what happened, for Android and iPhone.')" />

    @php
        $ago = fn ($t) => $t ? \Carbon\Carbon::createFromTimestamp((int) $t)->diffForHumans() : null;
        $cronOk = $health['scheduler_ran_at'] && time() - (int) $health['scheduler_ran_at'] < 240;
        $stuck = $health['waiting'] > 0 && ($health['oldest_seconds'] ?? 0) > 300;
    @endphp
    <div class="ff-card mb-4" id="delivery-health">
        <h2 class="h6 mb-1">{{ __('Are notifications leaving the server?') }}</h2>
        <p class="small text-ff-muted">{{ __('An announcement, an email or a push first waits in a queue. Something has to empty that queue every minute: the hosting’s cron job, or, when there is none, the first visit of each minute (the app opens many a minute, so it happens by itself).') }}</p>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-ff-muted">{{ __('Hosting cron job') }}</div>
                <div class="fw-semibold {{ $cronOk ? 'text-success' : 'text-warning' }}">
                    @if ($cronOk) <i class="bi bi-check-circle-fill"></i> {{ __('Running') }} · {{ $ago($health['scheduler_ran_at']) }}
                    @elseif ($health['scheduler_ran_at']) <i class="bi bi-exclamation-triangle-fill"></i> {{ __('Stopped') }} · {{ __('last ran :when', ['when' => $ago($health['scheduler_ran_at'])]) }}
                    @else <i class="bi bi-exclamation-triangle-fill"></i> {{ __('Not seen yet') }} @endif
                </div>
                @unless ($cronOk)<div class="small-xs text-ff-muted mt-1">{{ __('Add the cron job in the hosting panel (every minute: php artisan schedule:run). Until then visits to the app empty the queue.') }}</div>@endunless
            </div>
            <div class="col-md-4">
                <div class="small text-ff-muted">{{ __('Waiting to be sent') }}</div>
                <div class="fw-semibold {{ $stuck ? 'text-danger' : 'text-ff-black' }}">
                    {{ number_format($health['waiting']) }}
                    @if ($health['oldest_seconds'] && $health['waiting']) <span class="small fw-normal text-ff-muted">· {{ __('oldest :age', ['age' => \Carbon\CarbonInterval::seconds($health['oldest_seconds'])->cascade()->forHumans(['parts' => 2, 'short' => true])]) }}</span> @endif
                </div>
                @if ($stuck)<div class="small-xs text-danger mt-1">{{ __('Things are waiting for more than 5 minutes: nothing is emptying the queue. Open the app on a phone, or check the cron job.') }}</div>@endif
            </div>
            <div class="col-md-4">
                <div class="small text-ff-muted">{{ __('Emptied by a visit') }}</div>
                <div class="fw-semibold text-ff-black">{{ $health['drained_at'] ? $ago($health['drained_at']) : __('Not needed yet') }}</div>
                @if ($health['failed'])<div class="small-xs text-danger mt-1">{{ trans_choice(':n send failed and was given up on.|:n sends failed and were given up on.', $health['failed'], ['n' => $health['failed']]) }}</div>@endif
            </div>
        </div>
    </div>

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
