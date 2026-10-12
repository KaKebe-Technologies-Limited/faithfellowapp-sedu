<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $campaign->title }} — {{ __('QR poster') }}</title>
    @vite(['resources/scss/app.scss', 'resources/css/faithfellows.css'])
    <style>@media print { .no-print { display: none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }</style>
</head>
<body class="bg-white">
    <div class="mx-auto text-center p-5" style="max-width: 720px">
        <x-logo :size="40" class="justify-content-center mb-4" />
        <div class="small text-uppercase fw-bold text-ff-primary">{{ $campaign->church->name }}</div>
        <h1 class="display-5 fw-bold my-3">{{ $campaign->title }}</h1>
        @if ($campaign->short_description)<p class="fs-5 text-ff-muted">{{ $campaign->short_description }}</p>@endif
        <div class="ff-qr my-4 p-3" style="max-width: 420px">{!! $qr !!}</div>
        <p class="fs-4 fw-bold">{{ __('Scan to give with MoMo, Airtel Money or card') }}</p>
        <p class="text-ff-muted">{{ __('Goal: :goal', ['goal' => ff_money($campaign->goal_amount)]) }} · {{ Str::after(route('campaigns.show', $campaign), '://') }}</p>
        <button onclick="window.print()" class="btn btn-ff-primary no-print mt-3">{{ __('Print') }}</button>
    </div>
</body>
</html>
