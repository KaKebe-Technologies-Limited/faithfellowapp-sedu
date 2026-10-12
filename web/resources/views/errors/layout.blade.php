<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · FaithFellows</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/scss/app.scss', 'resources/css/faithfellows.css'])
    @endif
</head>
<body>
    <main class="ff-error">
        <div style="max-width: 520px">
            <a href="{{ url('/') }}" class="ff-logo justify-content-center mb-4" style="color:#BC2649">
                <x-logo-mark :size="40" />
            </a>
            <div class="ff-error-code">@yield('code')</div>
            <h1 class="h3 mt-2">@yield('title')</h1>
            <p class="text-ff-muted mb-4">@yield('message')</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="{{ url('/') }}" class="btn btn-ff-primary px-4">{{ __('Go home') }}</a>
                <a href="javascript:history.back()" class="btn btn-ff-outline px-4">{{ __('Go back') }}</a>
            </div>
        </div>
    </main>
</body>
</html>
