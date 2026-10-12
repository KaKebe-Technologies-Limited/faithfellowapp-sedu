<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ config('faithfellows.brand.name') }} — {{ __('Platform report') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #575757; }
        h1 { font-size: 18px; color: #1A1A1A; margin: 0 0 2px; }
        h2 { font-size: 12px; color: #BC2649; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .05em; }
        .muted { color: #8a8a8a; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px -6px 0; }
        .cards td { background: #FBEEF1; border-radius: 6px; padding: 8px 10px; vertical-align: top; }
        .cards .v { font-size: 14px; font-weight: bold; color: #1A1A1A; }
        .up { color: #1E9E5A; } .down { color: #D63031; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { background: #BC2649; color: #fff; text-align: left; padding: 5px; font-size: 9px; }
        table.list td { padding: 5px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .footer { margin-top: 24px; font-size: 8px; color: #a5a5a5; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ config('faithfellows.brand.name') }} — {{ __('Platform report') }}</h1>
    <div class="muted">{{ config('faithfellows.company.name') }} · {{ $data['from']->format('j M Y') }} – {{ now()->format('j M Y') }}</div>

    @php($labels = ['churches' => __('Active churches'), 'users' => __('Users'), 'giving' => __('Giving processed'), 'mrr' => __('MRR'), 'streams' => __('Live services'), 'sms' => __('SMS spend')])
    <table class="cards"><tr>
        @foreach ($data['headline'] as $key => $card)
            <td>
                <div class="muted">{{ $labels[$key] ?? $key }}</div>
                <div class="v">{{ ($card['money'] ?? false) ? ff_money($card['value']) : number_format($card['value']) }}</div>
                @if (($card['trend'] ?? null) !== null)<div class="{{ $card['trend'] >= 0 ? 'up' : 'down' }}">{{ $card['trend'] >= 0 ? '▲' : '▼' }} {{ abs($card['trend']) }}%</div>@endif
                @isset($card['note'])<div class="muted">{{ $card['note'] }}</div>@endisset
            </td>
        @endforeach
    </tr></table>

    <h2>{{ __('Largest churches') }}</h2>
    <table class="list">
        <thead><tr><th>{{ __('Church') }}</th><th>{{ __('City') }}</th><th>{{ __('Billing') }}</th><th class="right">{{ __('Members') }}</th><th class="right">{{ __('Giving') }}</th></tr></thead>
        <tbody>
            @foreach ($data['topChurches'] as $c)
                <tr><td>{{ $c->name }}</td><td>{{ $c->city }}</td><td>{{ $c->billing_status }}</td><td class="right">{{ number_format($c->members) }}</td><td class="right">{{ ff_money($c->giving) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Churches by district') }}</h2>
    <table class="list">
        <thead><tr><th>{{ __('District') }}</th><th class="right">{{ __('Churches') }}</th></tr></thead>
        <tbody>
            @foreach ($data['charts']['regions']['data']['labels'] as $i => $label)
                <tr><td>{{ $label }}</td><td class="right">{{ $data['charts']['regions']['data']['datasets'][0]['data'][$i] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    @if ($data['channels']->isNotEmpty())
        <h2>{{ __('Notification delivery') }}</h2>
        <table class="list">
            <thead><tr><th>{{ __('Channel') }}</th><th class="right">{{ __('Sent') }}</th><th class="right">{{ __('Failed') }}</th><th class="right">{{ __('Cost') }}</th></tr></thead>
            <tbody>
                @foreach ($data['channels'] as $d)
                    <tr><td>{{ $d->channel }}</td><td class="right">{{ $d->sent }}</td><td class="right">{{ $d->failed }}</td><td class="right">{{ ff_money($d->cost) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">{{ __('Generated on :date · Confidential', ['date' => now()->format('j M Y, H:i')]) }}</div>
</body>
</html>
