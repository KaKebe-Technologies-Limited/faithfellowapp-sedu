<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background:#F6F0F3; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color:#575757;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $lines[0] ?? $title }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F0F3; padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:24px; overflow:hidden;">
                <tr>
                    <td align="center" style="background:#7D1A39; background-image:linear-gradient(135deg,#4A0F29,#BC2649); padding:28px 24px;">
                        <img src="{{ url('/images/icon-192.png') }}" width="56" height="56" alt="FaithFellows" style="display:block; border-radius:16px; margin:0 auto 12px;">
                        <div style="font-size:22px; line-height:29px; font-weight:700; color:#ffffff;">{{ $title }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 28px 6px; font-size:15px; line-height:24px;">
                        @foreach ($lines as $line)
                            <p style="margin:0 0 14px;">{{ $line }}</p>
                        @endforeach
                    </td>
                </tr>
                @if (! empty($details))
                    <tr>
                        <td style="padding:2px 28px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; line-height:20px; background:#FFF3F8; border-radius:16px;">
                                @foreach ($details as $label => $value)
                                    <tr>
                                        <td style="padding:10px 16px; color:#8B7A83;">{{ $label }}</td>
                                        <td align="right" style="padding:10px 16px; font-weight:600; color:#1A1A1A;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif
                @if (! empty($button))
                    <tr>
                        <td align="center" style="padding:20px 28px 6px;">
                            <a href="{{ $button['url'] }}" style="display:inline-block; background:#BC2649; color:#ffffff; text-decoration:none; font-weight:600; font-size:15px; padding:14px 28px; border-radius:999px;">{{ $button['label'] }}</a>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:22px 28px 28px; font-size:12px; line-height:18px; color:#8B7A83;" align="center">
                        @if (! empty($note)){{ $note }}<br><br>@endif
                        <strong style="color:#BC2649;">FaithFellows</strong> · {{ config('faithfellows.brand.tagline') }}
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
