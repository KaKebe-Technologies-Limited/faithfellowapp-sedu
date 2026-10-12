@props(['title' => null, 'description' => null, 'image' => null, 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-ff.head :title="$title" :description="$description" :image="$image" />
</head>
<body @class(['ff-public', $bodyClass, 'ff-lite' => $ffLite ?? false])>
    <a href="#main" class="ff-skip-link">{{ __('Skip to content') }}</a>
    @include('partials.lite-banner')
    @include('partials.global-announcement')
    @include('partials.public-header')

    <main id="main">
        {{ $slot }}
    </main>

    @include('partials.public-footer')
    @include('partials.flash')
    @stack('scripts')
</body>
</html>
