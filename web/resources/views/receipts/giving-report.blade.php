<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Giving report') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #575757; }
        h1 { font-size: 16px; color: #1A1A1A; margin: 0 0 2px; }
        .brand { color: #BC2649; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #BC2649; color: #fff; text-align: left; padding: 5px; font-size: 8px; text-transform: uppercase; }
        td { padding: 4px 5px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .summary td { border: 0; padding: 2px 6px; }
    </style>
</head>
<body>
    <h1>{{ $church->name }} — {{ __('Giving report') }}</h1>
    <div>{{ $from ? \Illuminate\Support\Carbon::parse($from)->format('j M Y') : __('All time') }} – {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('j M Y') : now()->format('j M Y') }} · {{ __('Generated :d', ['d' => now()->format('j M Y H:i')]) }} · <span class="brand">FaithFellows</span></div>

    <table class="summary" style="width:auto">
        @foreach ($byCategory as $cat => $sum)
            <tr><td>{{ config('faithfellows.giving_categories.'.$cat, $cat) }}</td><td class="right"><strong>{{ ff_money($sum) }}</strong></td></tr>
        @endforeach
        <tr><td><strong>{{ __('Total') }}</strong></td><td class="right"><strong>{{ ff_money($rows->sum('amount')) }}</strong></td></tr>
    </table>

    <table>
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Giver') }}</th><th>{{ __('Category') }}</th><th>{{ __('Campaign') }}</th><th>{{ __('Method') }}</th><th class="right">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @foreach ($rows as $d)
                <tr><td>{{ $d->paid_at?->format('Y-m-d') }}</td><td>{{ $d->receipt_number }}</td><td>{{ $d->display_name }}</td><td>{{ $d->category_label }}</td><td>{{ $d->campaign?->title }}</td><td>{{ $d->method_label }}</td><td class="right">{{ ff_money($d->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
