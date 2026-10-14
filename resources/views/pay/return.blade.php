<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Back to FaithFellows') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #fafafa; color: #1a1a1a; }
        main { max-width: 360px; padding: 32px 24px; text-align: center; }
        .dot { width: 64px; height: 64px; border-radius: 50%; background: #ffe9f5; color: #bc2649; display: grid; place-items: center; margin: 0 auto 18px; font-size: 30px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { color: #727272; line-height: 1.5; margin: 0 0 22px; }
        a { display: inline-block; background: #bc2649; color: #fff; text-decoration: none; font-weight: 600; padding: 13px 26px; border-radius: 999px; }
    </style>
</head>
<body>
    <main>
        <div class="dot">{{ $cancelled ? '↩' : '✓' }}</div>
        <h1>{{ $cancelled ? __('Payment cancelled') : __('Thank you') }}</h1>
        <p>{{ $cancelled ? __('Nothing was charged. You can close this page and go back to the app.') : __('Your payment is being confirmed. Close this page and go back to the app to see your receipt.') }}</p>
        <a href="faithfellows://pay/{{ $reference }}">{{ __('Back to the app') }}</a>
    </main>
    <script>setTimeout(function () { window.location.href = 'faithfellows://pay/{{ $reference }}'; }, 800);</script>
</body>
</html>
