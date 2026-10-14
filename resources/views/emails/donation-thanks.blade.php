<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Thank you for your gift') }}</title>
</head>
<body style="margin:0; padding:0; background:#F6F0F3; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color:#575757;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('Thank you! :amount received by :church.', ['amount' => $amount, 'church' => $church]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F0F3; padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:24px; overflow:hidden;">
                <tr>
                    <td align="center" style="background:#7D1A39; background-image:linear-gradient(135deg,#4A0F29,#BC2649); padding:32px 24px 28px;">
                        <img src="{{ url('/images/icon-192.png') }}" width="64" height="64" alt="FaithFellows" style="display:block; border-radius:18px; margin:0 auto 14px;">
                        <div style="font-size:26px; line-height:32px; font-weight:700; color:#ffffff;">{{ __('Thank you, :name!', ['name' => $name]) }}</div>
                        <div style="font-size:15px; line-height:22px; color:#FFD9E4; margin-top:6px;">{{ __('Your gift has been received.') }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF3F8; border-radius:18px;">
                            <tr><td align="center" style="padding:22px 12px;">
                                <div style="font-size:13px; letter-spacing:1px; text-transform:uppercase; color:#8E1C37; font-weight:600;">{{ __('You gave') }}</div>
                                <div style="font-size:36px; line-height:44px; font-weight:800; color:#1A1A1A; margin-top:4px;">{{ $amount }}</div>
                                <div style="font-size:15px; color:#575757; margin-top:4px;">{{ __('to :church', ['church' => $church]) }}</div>
                            </td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 28px 4px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; line-height:20px;">
                            <tr><td style="padding:9px 0; color:#8B7A83; border-bottom:1px solid #F1E6EB;">{{ __('Given for') }}</td><td align="right" style="padding:9px 0; font-weight:600; color:#1A1A1A; border-bottom:1px solid #F1E6EB;">{{ $reason }}</td></tr>
                            @if ($campaign)<tr><td style="padding:9px 0; color:#8B7A83; border-bottom:1px solid #F1E6EB;">{{ __('Fundraiser') }}</td><td align="right" style="padding:9px 0; font-weight:600; color:#1A1A1A; border-bottom:1px solid #F1E6EB;">{{ $campaign->title }}</td></tr>@endif
                            <tr><td style="padding:9px 0; color:#8B7A83; border-bottom:1px solid #F1E6EB;">{{ __('Date') }}</td><td align="right" style="padding:9px 0; font-weight:600; color:#1A1A1A; border-bottom:1px solid #F1E6EB;">{{ $date }}</td></tr>
                            <tr><td style="padding:9px 0; color:#8B7A83; border-bottom:1px solid #F1E6EB;">{{ __('Paid with') }}</td><td align="right" style="padding:9px 0; font-weight:600; color:#1A1A1A; border-bottom:1px solid #F1E6EB;">{{ $method }}</td></tr>
                            <tr><td style="padding:9px 0; color:#8B7A83;">{{ __('Receipt number') }}</td><td align="right" style="padding:9px 0; font-weight:600; color:#1A1A1A;">{{ $receipt }}</td></tr>
                        </table>
                    </td>
                </tr>
                @if ($campaign)
                    <tr>
                        <td style="padding:12px 28px 4px;">
                            <div style="font-size:14px; font-weight:600; color:#1A1A1A; margin-bottom:8px;">{{ __('Together we have raised :raised of :goal', ['raised' => ff_money($campaign->raised_amount, $campaign->currency), 'goal' => ff_money($campaign->goal_amount, $campaign->currency)]) }}</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFE9F5; border-radius:6px;"><tr>
                                <td width="{{ max(3, min(100, (int) round($campaign->percent()))) }}%" style="background:#BC2649; height:10px; border-radius:6px; font-size:0; line-height:0;">&nbsp;</td>
                                <td style="font-size:0; line-height:0;">&nbsp;</td>
                            </tr></table>
                            <div style="font-size:12px; color:#8B7A83; margin-top:6px;">{{ round($campaign->percent()) }}% {{ __('of the goal') }}</div>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:20px 28px 4px;" align="center">
                        <div style="font-size:16px; line-height:24px; font-style:italic; color:#7D1A39;">“{{ __('God loves a cheerful giver.') }}”<br><span style="font-size:13px; font-style:normal; color:#8B7A83;">2 Corinthians 9:7</span></div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:22px 28px 8px;">
                        <a href="{{ $url }}" style="display:inline-block; background:#BC2649; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:14px 28px; border-radius:999px;">{{ __('View your giving history') }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 28px; font-size:12px; line-height:18px; color:#8B7A83;" align="center">
                        {{ __('Your receipt is attached as a PDF. Keep it for your records.') }}<br>
                        {{ __('You received this email because you gave through FaithFellows.') }}<br>
                        <strong style="color:#BC2649;">FaithFellows</strong> · {{ config('faithfellows.brand.tagline') }}
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
