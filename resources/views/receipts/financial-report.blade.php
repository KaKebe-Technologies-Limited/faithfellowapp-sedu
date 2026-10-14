<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Financial records') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #575757; }
        h1 { font-size: 16px; color: #1A1A1A; margin: 0 0 2px; }
        h2 { font-size: 11px; color: #1A1A1A; margin: 14px 0 2px; }
        .brand { color: #BC2649; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #BC2649; color: #fff; text-align: left; padding: 5px; font-size: 8px; text-transform: uppercase; }
        td { padding: 4px 5px; border-bottom: 1px solid #eee; }
        tfoot td { font-weight: bold; border-top: 1px solid #999; }
        .right { text-align: right; }
        .totals td { border: 0; padding: 2px 8px 2px 0; }
    </style>
</head>
<body>
    @php($cur = $report['currency'])
    <h1>{{ $church->name }} — {{ __('Financial records') }}</h1>
    <div>{{ $report['from']->format('j M Y') }} – {{ $report['to']->format('j M Y') }} · {{ __('Generated :d', ['d' => now()->format('j M Y H:i')]) }} · <span class="brand">FaithFellows</span></div>

    <table class="totals" style="width:auto; margin-top: 10px">
        <tr><td>{{ __('Received (gross)') }}</td><td class="right"><strong>{{ ff_money($report['gross'], $cur) }}</strong></td><td>{{ __(':n gifts from :g givers', ['n' => $report['gifts'], 'g' => $report['givers']]) }}</td></tr>
        <tr><td>{{ __('Fees to payment companies') }}</td><td class="right"><strong>{{ ff_money($report['fees'], $cur) }}</strong></td><td></td></tr>
        <tr><td>{{ __('Net to the church') }}</td><td class="right"><strong>{{ ff_money($report['net'], $cur) }}</strong></td><td></td></tr>
        <tr><td>{{ __('Collected online, not yet paid out') }}</td><td class="right"><strong>{{ ff_money($report['awaiting_payout']['amount'], $cur) }}</strong></td><td>{{ __(':n gifts', ['n' => $report['awaiting_payout']['count']]) }}</td></tr>
        <tr><td>{{ __('Refunded in this period') }}</td><td class="right"><strong>{{ ff_money($report['refunded']['amount'], $cur) }}</strong></td><td>{{ __(':n gifts', ['n' => $report['refunded']['count']]) }}</td></tr>
    </table>

    <h2>{{ __('Given for') }}</h2>
    <table>
        <thead><tr><th>{{ __('For') }}</th><th class="right">{{ __('Gifts') }}</th><th class="right">{{ __('Received') }}</th><th class="right">{{ __('Fees') }}</th><th class="right">{{ __('Net') }}</th><th class="right">{{ __('Share') }}</th></tr></thead>
        <tbody>
            @foreach ($report['by_reason'] as $row)
                <tr><td>{{ $row['label'] }}</td><td class="right">{{ $row['gifts'] }}</td><td class="right">{{ ff_money($row['gross'], $cur) }}</td><td class="right">{{ ff_money($row['fees'], $cur) }}</td><td class="right">{{ ff_money($row['net'], $cur) }}</td><td class="right">{{ $row['share'] }}%</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td>{{ __('Total') }}</td><td class="right">{{ $report['gifts'] }}</td><td class="right">{{ ff_money($report['gross'], $cur) }}</td><td class="right">{{ ff_money($report['fees'], $cur) }}</td><td class="right">{{ ff_money($report['net'], $cur) }}</td><td></td></tr></tfoot>
    </table>

    <h2>{{ __('Payment method') }}</h2>
    <table>
        <thead><tr><th>{{ __('Method') }}</th><th class="right">{{ __('Gifts') }}</th><th class="right">{{ __('Received') }}</th><th class="right">{{ __('Net') }}</th></tr></thead>
        <tbody>
            @foreach ($report['by_method'] as $row)
                <tr><td>{{ __($row['label']) }}</td><td class="right">{{ $row['gifts'] }}</td><td class="right">{{ ff_money($row['gross'], $cur) }}</td><td class="right">{{ ff_money($row['net'], $cur) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Ledger') }}</h2>
    <table>
        <thead><tr><th>{{ __('Paid') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Giver') }}</th><th>{{ __('Given for') }}</th><th>{{ __('Method') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Fee') }}</th></tr></thead>
        <tbody>
            @foreach ($ledger as $d)
                <tr><td>{{ $d->paid_at?->format('Y-m-d H:i') }}</td><td>{{ $d->receipt_number }}</td><td>{{ $d->display_name }}</td><td>{{ $d->campaign ? __('Fundraising: :t', ['t' => $d->campaign->title]) : $d->category_label }}</td><td>{{ $d->method_label }}</td><td class="right">{{ ff_money($d->amount, $d->currency) }}</td><td class="right">{{ ff_money($d->fee, $d->currency) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
