<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Receipt') }} {{ $donation->receipt_number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #575757; font-size: 11px; }
        .brand { color: #BC2649; }
        .header { border-bottom: 3px solid #BC2649; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: middle; }
        h1 { font-size: 20px; color: #1A1A1A; margin: 0; }
        .muted { color: #727272; }
        .amount { font-size: 28px; font-weight: bold; color: #BC2649; margin: 6px 0 2px; }
        table.details { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.details td { padding: 7px 0; border-bottom: 1px solid #F1F1F1; }
        table.details td.label { color: #727272; width: 42%; }
        table.details td.value { color: #1A1A1A; font-weight: bold; text-align: right; }
        .thanks { margin-top: 20px; padding: 12px 14px; background: #FFE9F5; border-radius: 8px; color: #8E1C37; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #A5A5A5; text-align: center; }
        .stamp { display: inline-block; padding: 3px 10px; border: 2px solid #1E9E5A; color: #1E9E5A; border-radius: 6px; font-weight: bold; font-size: 10px; letter-spacing: 1px; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td width="60">
                @if ($churchLogo)<img src="{{ $churchLogo }}" width="52" height="52" style="border-radius: 10px">@endif
            </td>
            <td>
                <h1>{{ $church->name }}</h1>
                <div class="muted">{{ $church->address }} {{ $church->location }}@if ($church->phone) · {{ $church->phone }}@endif</div>
            </td>
            <td align="right" width="140">
                <div class="muted">{{ __('Official receipt') }}</div>
                <div style="font-weight:bold;color:#1A1A1A">{{ $donation->receipt_number }}</div>
            </td>
        </tr>
    </table>

    <div class="muted">{{ __('Amount received') }}</div>
    <div class="amount">{{ ff_money($donation->amount, $donation->currency) }}</div>
    <span class="stamp">{{ $donation->status === 'refunded' ? __('REFUNDED') : __('PAID') }}</span>

    <table class="details">
        <tr><td class="label">{{ __('Received from') }}</td><td class="value">{{ $donation->display_name }}</td></tr>
        <tr><td class="label">{{ __('Category') }}</td><td class="value">{{ $donation->category_label }}</td></tr>
        @if ($donation->campaign)
            <tr><td class="label">{{ __('Campaign') }}</td><td class="value">{{ $donation->campaign->title }}</td></tr>
        @endif
        <tr><td class="label">{{ __('Date') }}</td><td class="value">{{ $donation->paid_at?->timezone(config('app.timezone'))->format('j F Y, H:i') }}</td></tr>
        <tr><td class="label">{{ __('Payment method') }}</td><td class="value">{{ $donation->method_label }}</td></tr>
        <tr><td class="label">{{ __('Transaction reference') }}</td><td class="value">{{ $donation->provider_ref ?: $donation->reference }}</td></tr>
        <tr><td class="label">{{ __('FaithFellows reference') }}</td><td class="value">{{ $donation->reference }}</td></tr>
    </table>

    <div class="thanks">
        “{{ __('Each of you should give what you have decided in your heart to give… for God loves a cheerful giver.') }}” — 2 Corinthians 9:7<br>
        <strong>{{ __('Thank you for your generosity!') }}</strong>
    </div>

    <div class="footer">
        @if ($ffLogo)<img src="{{ $ffLogo }}" width="12" height="12" style="vertical-align: middle">@endif
        <span class="brand">FaithFellows</span> · {{ config('faithfellows.company.name') }} · {{ config('faithfellows.company.email') }}
        <br>{{ __('This receipt was generated electronically and is valid without a signature.') }}
    </div>
</body>
</html>
