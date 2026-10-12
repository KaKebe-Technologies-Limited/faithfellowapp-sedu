<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $campaign->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #575757; }
        h1 { font-size: 16px; color: #1A1A1A; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #BC2649; color: #fff; text-align: left; padding: 5px; font-size: 9px; }
        td { padding: 5px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $campaign->title }}</h1>
    <div>{{ $church->name }} · {{ __('Raised :r of :g (:p%)', ['r' => ff_money($campaign->raised_amount), 'g' => ff_money($campaign->goal_amount), 'p' => round($campaign->percent())]) }} · {{ now()->format('j M Y') }}</div>
    <table>
        <thead><tr><th>#</th><th>{{ __('Date') }}</th><th>{{ __('Giver') }}</th><th>{{ __('Method') }}</th><th>{{ __('Receipt') }}</th><th class="right">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @foreach ($rows as $i => $d)
                <tr><td>{{ $i + 1 }}</td><td>{{ $d->paid_at?->format('Y-m-d') }}</td><td>{{ $d->is_anonymous ? __('Anonymous').' ('.($d->giver_name ?: $d->user?->name).')' : ($d->giver_name ?: $d->user?->name) }}</td><td>{{ $d->method_label }}</td><td>{{ $d->receipt_number }}</td><td class="right">{{ ff_money($d->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
