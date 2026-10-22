<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Join :church', ['church' => $church->name]) }} · {{ config('faithfellows.brand.name', 'FaithFellows') }}</title>
    @vite(['resources/scss/app.scss', 'resources/css/faithfellows.css'])
    <style>
        body { background: #fff; }
        .ff-qr-poster { max-width: 620px; margin: 32px auto; text-align: center; padding: 40px 32px; border: 3px solid {{ $church->brand_color ?: '#BC2649' }}; border-radius: 28px; }
        .ff-qr-poster svg { width: 100%; max-width: 420px; height: auto; }
        .ff-poster-steps { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin: 0 0 22px; padding: 0; list-style: none; }
        .ff-poster-steps li { flex: 1 1 150px; max-width: 190px; font-size: .95rem; }
        .ff-poster-steps b { display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; border-radius: 50%; background: {{ $church->brand_color ?: '#BC2649' }}; color: #fff; margin-bottom: 6px; }
        @media print { .no-print { display: none !important; } .ff-qr-poster { margin: 0 auto; border-width: 2px; } }
    </style>
</head>
<body>
    <main class="ff-qr-poster">
        <x-ff.church-avatar :church="$church" size="xl" class="mb-3" />
        <h1 class="display-6 fw-bold text-ff-black mb-1">{{ __('Join :church', ['church' => $church->name]) }}</h1>
        <p class="fs-5 mb-4">{{ __('Sermons, events, prayer and giving, in your pocket') }}</p>
        <ol class="ff-poster-steps">
            <li><b>1</b><br>{{ __('Scan the code with your phone camera') }}</li>
            <li><b>2</b><br>{{ __('Get the FaithFellows app') }}</li>
            <li><b>3</b><br>{{ __('Create your account and you are in') }}</li>
        </ol>
        <div class="mb-3">{!! $qr !!}</div>
        <p class="mb-1 fw-semibold text-ff-black">{{ preg_replace('~^https?://~', '', $url) }}</p>
        <p class="small text-ff-muted mb-0">{{ __('Church code') }}: <strong>{{ $code }}</strong> · {{ config('faithfellows.brand.name', 'FaithFellows') }}</p>
    </main>
    <div class="text-center no-print mb-5">
        <button onclick="window.print()" class="btn btn-ff-primary btn-lg"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
        <a href="{{ route('church-admin.invite.show', $church) }}" class="btn btn-ff-ghost btn-lg">{{ __('Back') }}</a>
    </div>
</body>
</html>
