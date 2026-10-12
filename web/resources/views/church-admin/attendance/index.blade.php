<x-layouts.church-admin :church="$church" :title="__('Attendance')">
    <x-ff.page-header :title="__('Attendance')" :subtitle="__('Members check in by scanning the QR code at church or with their location. Record guests and head counts manually.')">
        <a href="{{ route('church-admin.attendance.qr', $church) }}" class="btn btn-ff-primary" target="_blank"><i class="bi bi-qr-code"></i> {{ __('Today’s QR code') }}</a>
        <a href="{{ route('church-admin.attendance.export', $church) }}" class="btn btn-ff-soft"><i class="bi bi-download"></i> {{ __('Export CSV') }}</a>
    </x-ff.page-header>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Weekly attendance')" :subtitle="__('Last 12 weeks, all services')">
                @if (count($chart['data']['labels']))
                    <div style="height: 260px"><canvas data-ff-chart="{{ json_encode($chart) }}" role="img" aria-label="{{ __('Weekly attendance chart') }}"></canvas></div>
                @else
                    <x-ff.empty-state icon="bi-bar-chart" :title="__('No attendance recorded yet')" :message="__('Print today’s QR code and place it at the entrance.')" class="border-0" />
                @endif
            </x-ff.card>
        </div>
        <div class="col-lg-4 d-grid gap-3 align-content-start">
            @foreach (['qr' => [__('QR check-ins'), 'bi-qr-code-scan'], 'geofence' => [__('Location check-ins'), 'bi-geo-alt'], 'manual' => [__('Recorded manually'), 'bi-pencil']] as $method => [$label, $icon])
                <x-ff.stat-card :label="$label" :value="number_format($methods[$method] ?? 0)" :icon="$icon">{{ __('last 12 weeks') }}</x-ff.stat-card>
            @endforeach
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <x-ff.card flush>
                <x-slot:actions>
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="{{ __('Service date') }}">
                    </form>
                </x-slot:actions>
                <div class="px-4 pt-4"><h2 class="h6 mb-0">{{ __(':date — :n present', ['date' => $date->format('D j M'), 'n' => number_format($records->sum('headcount'))]) }}</h2></div>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Service') }}</th><th>{{ __('How') }}</th><th class="text-end">{{ __('Count') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($records as $r)
                                <tr>
                                    <td>@if ($r->user)<span class="d-flex align-items-center gap-2"><x-ff.avatar :user="$r->user" size="xs" /> {{ $r->user->name }}</span>@else{{ $r->guest_name ?: __('Head count') }} <span class="ff-tag">{{ __('Guest') }}</span>@endif</td>
                                    <td class="small">{{ $r->serviceTime?->name ?? '—' }}</td>
                                    <td class="small">{{ ['qr' => __('QR'), 'geofence' => __('Location'), 'manual' => __('Manual')][$r->method] }}</td>
                                    <td class="text-end">{{ $r->headcount }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('church-admin.attendance.destroy', [$church, $r]) }}" data-ff-confirm="{{ __('Remove this record?') }}">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-ff-muted small py-4">{{ __('No one recorded for this date.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
        </div>

        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Record attendance')">
                <form method="POST" action="{{ route('church-admin.attendance.store', $church) }}" x-data="{ guest: false }">@csrf
                    <x-ff.input type="date" name="service_date" :label="__('Date')" :value="$date->toDateString()" max="{{ today()->toDateString() }}" required />
                    @if ($serviceTimes->isNotEmpty())
                        <x-ff.select name="service_time_id" :label="__('Service')" :options="$serviceTimes->mapWithKeys(fn ($s) => [$s->id => $s->day_name.' · '.$s->name])" :placeholder="__('— Any —')" />
                    @endif
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="guest-toggle" x-model="guest"><label class="form-check-label" for="guest-toggle">{{ __('Guest or head count') }}</label></div>
                    <div x-show="!guest"><x-ff.select name="user_id" :label="__('Member')" :options="$people" :placeholder="__('— Choose —')" /></div>
                    <div x-show="guest" x-cloak>
                        <x-ff.input name="guest_name" :label="__('Name or group')" maxlength="120" :placeholder="__('e.g. Children’s church')" />
                        <x-ff.input type="number" name="headcount" :label="__('How many?')" value="1" min="1" max="5000" />
                    </div>
                    <button class="btn btn-ff-primary w-100">{{ __('Save') }}</button>
                </form>
            </x-ff.card>

            <x-ff.card :title="__('We miss you')" :subtitle="__('Regular attenders absent for 3+ weeks')">
                @forelse ($absentees as $u)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$u" size="sm" />
                        <span class="flex-grow-1 small text-truncate">{{ $u->name }}</span>
                        @if ($u->phone)<a href="tel:{{ $u->phone }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Call :name', ['name' => $u->name]) }}"><i class="bi bi-telephone"></i></a>@endif
                        @if (auth()->user()->canInChurch($church, 'care.manage'))
                            <a href="{{ route('church-admin.care.member', [$church, $u->id]) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Care record') }}"><i class="bi bi-heart-pulse"></i></a>
                        @endif
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('Everyone is accounted for. 🙌') }}</p>
                @endforelse
            </x-ff.card>
        </div>
    </div>
</x-layouts.church-admin>
