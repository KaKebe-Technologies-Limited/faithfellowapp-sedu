@php
    // route => [label, icon, active pattern]
    $settingsTabs = [
        'church-admin.settings.profile' => [__('Profile'), 'bi-building', 'church-admin.settings.profile*'],
        'church-admin.branches.index' => [__('Branches'), 'bi-diagram-2', 'church-admin.branches.*'],
        'church-admin.settings.branding' => [__('Branding'), 'bi-palette', 'church-admin.settings.branding*'],
        'church-admin.settings.website' => [__('Website builder'), 'bi-layout-wtf', 'church-admin.settings.website*'],
        'church-admin.settings.giving' => [__('Giving channels'), 'bi-wallet2', 'church-admin.settings.giving*'],
        'church-admin.settings.integrations' => [__('Integrations'), 'bi-plug', 'church-admin.settings.integrations*'],
    ];
@endphp
<nav class="ff-tabs mb-4" aria-label="{{ __('Settings') }}">
    @foreach ($settingsTabs as $routeName => [$label, $icon, $pattern])
        @if (Route::has($routeName))
            <a href="{{ route($routeName, $church) }}" @class(['ff-tab', 'active' => request()->routeIs($pattern)])><i class="bi {{ $icon }} me-1"></i>{{ $label }}</a>
        @endif
    @endforeach
</nav>
