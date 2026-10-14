<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $church->name }} — {{ __('Analytics report') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #575757; }
        h1 { font-size: 18px; color: #1A1A1A; margin: 0 0 2px; }
        h2 { font-size: 12px; color: #BC2649; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .05em; }
        .muted { color: #8a8a8a; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
        .cards td { background: #FBEEF1; border-radius: 6px; padding: 8px 10px; width: 16%; vertical-align: top; }
        .cards .v { font-size: 15px; font-weight: bold; color: #1A1A1A; }
        .up { color: #1E9E5A; } .down { color: #D63031; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { background: #BC2649; color: #fff; text-align: left; padding: 5px; font-size: 9px; }
        table.list td { padding: 5px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .footer { margin-top: 24px; font-size: 8px; color: #a5a5a5; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $church->name }}</h1>
    <div class="muted">{{ __('Analytics report') }} · {{ __(\App\Http\Controllers\ChurchAdmin\AnalyticsController::RANGES[$days]) }} ({{ $data['from']->format('j M Y') }} – {{ now()->format('j M Y') }})</div>

    @php
        $labels = ['members' => __('Members'), 'engagement' => __('Content views'), 'attendance' => __('Avg weekly attendance'), 'live' => __('Live peak viewers'), 'prayer' => __('Prayer requests'), 'giving' => __('Giving')];
    @endphp
    <table class="cards"><tr>
        @foreach ($data['headline'] as $key => $card)
            <td>
                <div class="muted">{{ $labels[$key] ?? $key }}</div>
                <div class="v">{{ ($card['money'] ?? false) ? ff_money($card['value']) : number_format($card['value']) }}</div>
                @isset($card['trend'])@if ($card['trend'] !== null)<div class="{{ $card['trend'] >= 0 ? 'up' : 'down' }}">{{ $card['trend'] >= 0 ? '▲' : '▼' }} {{ abs($card['trend']) }}%</div>@endif @endisset
                @isset($card['note'])<div class="muted">{{ $card['note'] }}</div>@endisset
            </td>
        @endforeach
    </tr></table>

    <h2>{{ __('Top sermons') }}</h2>
    <table class="list">
        <thead><tr><th>{{ __('Sermon') }}</th><th>{{ __('Preacher') }}</th><th class="right">{{ __('Views') }}</th><th class="right">{{ __('Listens') }}</th><th class="right">{{ __('Downloads') }}</th></tr></thead>
        <tbody>
            @forelse ($data['topSermons'] as $s)
                <tr><td>{{ $s->title }}</td><td>{{ $s->preacher }}</td><td class="right">{{ $s->views }}</td><td class="right">{{ $s->listens }}</td><td class="right">{{ $s->downloads }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('No sermon activity in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('Events') }}</h2>
    <table class="list">
        <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Date') }}</th><th class="right">{{ __('RSVPs') }}</th><th class="right">{{ __('Checked in') }}</th></tr></thead>
        <tbody>
            @forelse ($data['topEvents'] as $e)
                <tr><td>{{ $e->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($e->starts_at)->format('j M Y') }}</td><td class="right">{{ $e->rsvps }}</td><td class="right">{{ $e->checked_in }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('No events in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('Live services') }}</h2>
    <table class="list">
        <thead><tr><th>{{ __('Service') }}</th><th>{{ __('Date') }}</th><th class="right">{{ __('Peak viewers') }}</th><th class="right">{{ __('Unique viewers') }}</th></tr></thead>
        <tbody>
            @forelse ($data['liveStreams'] as $s)
                <tr><td>{{ $s->title }}</td><td>{{ \Illuminate\Support\Carbon::parse($s->started_at)->format('j M Y') }}</td><td class="right">{{ $s->peak_viewers }}</td><td class="right">{{ $s->unique }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">{{ __('No live services in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($data['groups']->isNotEmpty())
        <h2>{{ __('Small group attendance') }}</h2>
        <table class="list">
            <thead><tr><th>{{ __('Group') }}</th><th class="right">{{ __('Present') }}</th><th class="right">{{ __('Recorded') }}</th><th class="right">{{ __('Rate') }}</th></tr></thead>
            <tbody>
                @foreach ($data['groups'] as $g)
                    <tr><td>{{ $g->name }}</td><td class="right">{{ $g->present }}</td><td class="right">{{ $g->total }}</td><td class="right">{{ $g->total ? round($g->present / $g->total * 100) : 0 }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($data['delivery']->isNotEmpty())
        <h2>{{ __('Notifications delivered') }}</h2>
        <table class="list">
            <thead><tr><th>{{ __('Channel') }}</th><th class="right">{{ __('Sent') }}</th><th class="right">{{ __('Failed') }}</th><th class="right">{{ __('Cost') }}</th></tr></thead>
            <tbody>
                @foreach ($data['delivery'] as $d)
                    <tr><td>{{ __(config('faithfellows.notification_channels.'.$d->channel, $d->channel)) }}</td><td class="right">{{ $d->sent }}</td><td class="right">{{ $d->failed }}</td><td class="right">{{ ff_money($d->cost) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">{{ __('Generated by :brand on :date', ['brand' => config('faithfellows.brand.name', 'FaithFellows'), 'date' => now()->format('j M Y, H:i')]) }}</div>
</body>
</html>
