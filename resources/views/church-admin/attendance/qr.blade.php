<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Check in') }} · {{ $church->name }}</title>
    @vite(['resources/scss/app.scss', 'resources/css/faithfellows.css'])
    <style>
        body { background: #fff; }
        .ff-qr-poster { max-width: 620px; margin: 32px auto; text-align: center; padding: 40px 32px; border: 3px solid {{ $church->brand_color ?: '#BC2649' }}; border-radius: 28px; }
        .ff-qr-poster svg { width: 100%; max-width: 420px; height: auto; }
        @media print { .no-print { display: none !important; } .ff-qr-poster { margin: 0 auto; border-width: 2px; } }
    </style>
</head>
<body>
    <main class="ff-qr-poster">
        <x-ff.church-avatar :church="$church" size="xl" class="mb-3" />
        <h1 class="display-6 fw-bold text-ff-black mb-1">{{ __('Welcome to :church', ['church' => $church->name]) }}</h1>
        <p class="fs-5 mb-4">{{ __('Scan to check in for today’s service') }}</p>
        <div class="mb-4">{!! $qr !!}</div>
        <p class="small text-ff-muted mb-1">{{ __('Open your phone camera and point it at the code. Valid :date only.', ['date' => today()->format('l j F Y')]) }}</p>
        <p class="small text-ff-muted mb-0">{{ config('faithfellows.brand.name', 'FaithFellows') }}</p>
    </main>
    <div class="text-center no-print mb-5">
        <button onclick="window.print()" class="btn btn-ff-primary btn-lg"><i class="bi bi-printer"></i> {{ __('Print') }}</button>
        <a href="{{ route('church-admin.attendance.index', $church) }}" class="btn btn-ff-ghost btn-lg">{{ __('Back') }}</a>
    </div>
</body>
</html>
