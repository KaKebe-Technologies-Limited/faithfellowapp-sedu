{{-- Shared shell for member / church admin / super admin areas. --}}
@props(['title' => null, 'nav' => [], 'area' => 'member', 'searchAction' => null, 'searchPlaceholder' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-ff.head :title="$title" :noindex="true" />
    <script>try { if (localStorage.getItem('ff-sidebar-collapsed') === '1') document.documentElement.classList.add('ff-sidebar-collapsed'); } catch (e) { /* the menu simply starts open */ }</script>
</head>
<body @class(['ff-app', 'ff-area-'.$area, 'ff-lite' => $ffLite ?? false])>
    <a href="#main" class="ff-skip-link">{{ __('Skip to content') }}</a>

    @if (session('impersonator_id'))
        <div class="ff-impersonation-bar">
            <i class="bi bi-incognito me-1"></i>
            {{ __('You are impersonating :name.', ['name' => auth()->user()->name]) }}
            <form method="POST" action="{{ route('impersonate.stop') }}" class="d-inline">
                @csrf
                <button class="btn btn-sm btn-dark rounded-pill ms-2 py-0">{{ __('Stop impersonating') }}</button>
            </form>
        </div>
    @endif
    @if ($area === 'church-admin' && auth()->user()?->isSuperAdmin() && ! session('impersonator_id') && app()->bound('currentChurch'))
        <div class="ff-impersonation-bar">
            <i class="bi bi-shield-lock me-1"></i>
            {{ __('You are managing :church as the super admin. What you change is recorded.', ['church' => app('currentChurch')->name]) }}
            <a href="{{ route('super-admin.churches.show', app('currentChurch')) }}" class="btn btn-sm btn-dark rounded-pill ms-2 py-0">{{ __('Back to Super admin') }}</a>
        </div>
    @endif
    @include('partials.lite-banner')
    @include('partials.global-announcement')

    <aside class="ff-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-label="{{ __('Sidebar') }}">
        <div class="offcanvas-body">
            <div class="ff-sidebar-brand justify-content-between">
                <x-logo :href="url('/')" :size="30" />
                <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="{{ __('Close') }}"></button>
            </div>
            @isset($sidebarTop)
                <div class="px-3 pt-3 ff-sidebar-top">{{ $sidebarTop }}</div>
            @endisset
            <nav class="ff-sidebar-nav" aria-label="{{ __('Section navigation') }}">
                @foreach ($nav as $section)
                    @php($items = collect($section['items'])->filter(fn ($i) => Route::has($i['route']) && ($i['show'] ?? true)))
                    @if ($items->isNotEmpty())
                        @if (! empty($section['heading']))
                            <div class="ff-sidebar-heading">{{ $section['heading'] }}</div>
                        @endif
                        @foreach ($items as $item)
                            @php($active = request()->routeIs(...(array) ($item['active'] ?? $item['route'])))
                            <a href="{{ route($item['route'], $item['params'] ?? []) }}" @class(['ff-nav-link', 'active' => $active]) title="{{ $item['label'] }}" @if ($active) aria-current="page" @endif>
                                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span class="ff-nav-label">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="badge rounded-pill text-bg-primary">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
            <div class="ff-sidebar-footer">
                {{ $sidebarFooter ?? '' }}
                <a href="#" class="ff-nav-link mb-1 d-none d-lg-flex" data-ff-sidebar-toggle title="{{ __('Collapse the menu') }}">
                    <i class="bi bi-chevron-bar-left ff-when-open" aria-hidden="true"></i>
                    <i class="bi bi-chevron-bar-right ff-when-collapsed" aria-hidden="true"></i>
                    <span class="ff-nav-label ff-when-open">{{ __('Collapse menu') }}</span>
                </a>
                <a href="#" class="ff-nav-link mb-0" data-ff-lite-toggle title="{{ ($ffLite ?? false) ? __('Full mode') : __('Data-saver mode') }}">
                    <i class="bi bi-reception-{{ ($ffLite ?? false) ? '4' : '2' }}"></i>
                    <span class="ff-nav-label">{{ ($ffLite ?? false) ? __('Full mode') : __('Data-saver mode') }}</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="ff-main">
        <header class="ff-topbar">
            <button class="btn btn-ff-ghost btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="{{ __('Open menu') }}">
                <i class="bi bi-list fs-4"></i>
            </button>
            <button class="btn btn-ff-ghost btn-icon d-none d-lg-inline-flex ff-sidebar-toggle" type="button" data-ff-sidebar-toggle aria-controls="appSidebar" aria-expanded="true" aria-label="{{ __('Collapse the menu') }}" title="{{ __('Collapse the menu') }}">
                <i class="bi bi-chevron-bar-left fs-5 ff-when-open" aria-hidden="true"></i>
                <i class="bi bi-chevron-bar-right fs-5 ff-when-collapsed" aria-hidden="true"></i>
            </button>

            @if ($searchAction)
                <form class="ff-topbar-search d-none d-sm-block" action="{{ $searchAction }}" method="GET" role="search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <label for="topbar-search" class="visually-hidden">{{ __('Search') }}</label>
                    <input id="topbar-search" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $searchPlaceholder ?? __('Search…') }}">
                </form>
            @else
                <div class="flex-grow-1 min-w-0">{{ $topbarStart ?? '' }}</div>
            @endif

            <div class="ms-auto d-flex align-items-center gap-1">
                {{ $topbarEnd ?? '' }}

                @if (Route::has('notifications.index'))
                    <a href="{{ route('notifications.index') }}" class="btn btn-ff-ghost btn-icon ff-bell" aria-label="{{ __('Notifications') }}"
                       x-data="notificationBell({ count: {{ (int) ($unreadNotifications ?? 0) }}, url: '{{ Route::has('notifications.count') ? route('notifications.count') : '' }}' })">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="ff-bell-count" x-show="count > 0" x-text="count > 99 ? '99+' : count" @if (! ($unreadNotifications ?? 0)) style="display:none" @endif>{{ $unreadNotifications ?? 0 }}</span>
                    </a>
                @endif

                <div class="dropdown">
                    <button class="btn btn-ff-ghost d-flex align-items-center gap-2 px-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Account menu') }}">
                        <x-ff.avatar :user="auth()->user()" size="sm" />
                        <span class="d-none d-md-inline fw-semibold text-ff-black small">{{ auth()->user()->first_name }}</span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 240px">
                        <li class="px-3 py-2">
                            <div class="fw-semibold text-ff-black">{{ auth()->user()->name }}</div>
                            <div class="small text-ff-muted text-truncate">{{ auth()->user()->email }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @if (Route::has('dashboard'))
                            <li><a class="dropdown-item" href="{{ route('dashboard') }}"><i class="bi bi-house me-2"></i>{{ __('Member area') }}</a></li>
                        @endif
                        @foreach ($staffChurches ?? [] as $staffChurch)
                            <li><a class="dropdown-item" href="{{ route('church-admin.dashboard', $staffChurch) }}"><i class="bi bi-building-gear me-2"></i>{{ Str::limit($staffChurch->name, 26) }}</a></li>
                        @endforeach
                        @role('super_admin')
                            <li><a class="dropdown-item" href="{{ route('super-admin.dashboard') }}"><i class="bi bi-shield-lock me-2"></i>{{ __('Super admin') }}</a></li>
                        @endrole
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>{{ __('Profile & settings') }}</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Log out') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="ff-content" id="main">
            {{ $slot }}
        </main>
    </div>

    @include('partials.flash')
    <script>
        // Collapse the menu to a rail of icons (and open it again). The choice is remembered on this device.
        (() => {
            const root = document.documentElement;
            const buttons = document.querySelectorAll('[data-ff-sidebar-toggle]');
            const words = { collapse: @json(__('Collapse the menu')), expand: @json(__('Expand the menu')) };
            const show = () => {
                const collapsed = root.classList.contains('ff-sidebar-collapsed');
                buttons.forEach((b) => { b.setAttribute('aria-expanded', String(!collapsed)); b.title = collapsed ? words.expand : words.collapse; if (b.matches('button')) b.setAttribute('aria-label', b.title); });
            };
            buttons.forEach((b) => b.addEventListener('click', (e) => {
                e.preventDefault();
                root.classList.toggle('ff-sidebar-collapsed');
                try { localStorage.setItem('ff-sidebar-collapsed', root.classList.contains('ff-sidebar-collapsed') ? '1' : '0'); } catch (err) { /* not remembered */ }
                show();
            }));
            show();
        })();
    </script>
    @stack('scripts')
</body>
</html>
