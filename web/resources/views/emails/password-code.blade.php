<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Your password reset code') }}</title>
</head>
<body style="margin:0; padding:0; background:#F6F0F3; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color:#575757;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('Your code is :code. It works for 15 minutes.', ['code' => $code]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F0F3; padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border-radius:24px; overflow:hidden;">
                <tr>
                    <td align="center" style="background:#7D1A39; background-image:linear-gradient(135deg,#4A0F29,#BC2649); padding:28px 24px;">
                        <img src="{{ url('/images/icon-192.png') }}" width="56" height="56" alt="FaithFellows" style="display:block; border-radius:16px; margin:0 auto 12px;">
                        <div style="font-size:22px; line-height:28px; font-weight:700; color:#ffffff;">{{ __('Reset your password') }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px; font-size:15px; line-height:23px;">
                        {{ __('Hello :name,', ['name' => $name]) }}<br><br>
                        {{ __('Enter this code in the FaithFellows app to choose a new password:') }}
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:8px 28px 8px;">
                        <div style="display:inline-block; background:#FFF3F8; border-radius:16px; padding:16px 28px; font-size:36px; letter-spacing:10px; font-weight:800; color:#BC2649;">{{ $code }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 28px 28px; font-size:13px; line-height:20px; color:#8B7A83;" align="center">
                        {{ __('The code works for 15 minutes. If you did not ask to reset your password, you can ignore this email: your password has not changed.') }}<br><br>
                        <strong style="color:#BC2649;">FaithFellows</strong>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
