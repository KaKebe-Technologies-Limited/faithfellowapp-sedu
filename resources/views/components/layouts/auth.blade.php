@props(['title' => null, 'wide' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-ff.head :title="$title" :noindex="true" />
</head>
<body @class(['ff-lite' => $ffLite ?? false])>
    <main class="ff-auth" id="main">
        <div class="w-100" style="max-width: {{ $wide ? '640px' : '460px' }}">
            <div class="text-center mb-4">
                <x-logo :href="route('home')" :size="40" />
            </div>
            <div class="ff-auth-card" style="max-width: none">
                {{ $slot }}
            </div>
            <p class="text-center small text-ff-muted mt-4 mb-0">
                &copy; {{ date('Y') }} {{ config('faithfellows.company.name') }} ·
                <a href="{{ route('privacy') }}" class="text-ff-muted">{{ __('Privacy') }}</a> ·
                <a href="{{ route('terms') }}" class="text-ff-muted">{{ __('Terms') }}</a>
            </p>
        </div>
    </main>
    @include('partials.flash')
</body>
</html>
