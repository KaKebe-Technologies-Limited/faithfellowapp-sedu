@props(['title' => null, 'church'])
@php
    $user = auth()->user();
    $can = fn (string $perm) => $user->canInChurch($church, $perm);
    $p = ['church' => $church];
    $nav = [
        ['heading' => null, 'items' => [
            ['label' => __('Dashboard'), 'icon' => 'bi-speedometer2', 'route' => 'church-admin.dashboard', 'params' => $p],
            ['label' => __('Analytics'), 'icon' => 'bi-graph-up-arrow', 'route' => 'church-admin.analytics', 'params' => $p, 'show' => $can('analytics.view')],
        ]],
        ['heading' => __('Content'), 'items' => [
            ['label' => __('Sermons'), 'icon' => 'bi-mic', 'route' => 'church-admin.sermons.index', 'params' => $p, 'active' => ['church-admin.sermons.*', 'church-admin.series.*'], 'show' => $can('content.manage')],
            ['label' => __('Devotionals'), 'icon' => 'bi-book', 'route' => 'church-admin.devotionals.index', 'params' => $p, 'active' => 'church-admin.devotionals.*', 'show' => $can('content.manage')],
            ['label' => __('Announcements'), 'icon' => 'bi-megaphone', 'route' => 'church-admin.announcements.index', 'params' => $p, 'active' => 'church-admin.announcements.*', 'show' => $can('content.manage')],
            ['label' => __('Reading plans'), 'icon' => 'bi-journal-check', 'route' => 'church-admin.reading-plans.index', 'params' => $p, 'active' => 'church-admin.reading-plans.*', 'show' => $can('content.manage')],
            ['label' => __('Media library'), 'icon' => 'bi-images', 'route' => 'church-admin.media.index', 'params' => $p, 'active' => ['church-admin.media.*', 'church-admin.playlists.*'], 'show' => $can('media.manage')],
            ['label' => __('Feed posts'), 'icon' => 'bi-postcard-heart', 'route' => 'church-admin.posts.index', 'params' => $p, 'active' => 'church-admin.posts.*', 'show' => $can('community.moderate')],
        ]],
        ['heading' => __('Live & events'), 'items' => [
            ['label' => __('Live streams'), 'icon' => 'bi-broadcast', 'route' => 'church-admin.live.index', 'params' => $p, 'active' => 'church-admin.live.*', 'show' => $can('live.manage')],
            ['label' => __('Events'), 'icon' => 'bi-calendar-event', 'route' => 'church-admin.events.index', 'params' => $p, 'active' => 'church-admin.events.*', 'show' => $can('events.manage')],
        ]],
        ['heading' => __('Giving'), 'items' => [
            ['label' => __('Finance'), 'icon' => 'bi-cash-coin', 'route' => 'church-admin.giving.index', 'params' => $p, 'active' => ['church-admin.giving.*', 'church-admin.pledges.*', 'church-admin.payouts.*', 'church-admin.reconciliation.*'], 'show' => $can('giving.view')],
            ['label' => __('Campaigns'), 'icon' => 'bi-flag', 'route' => 'church-admin.campaigns.index', 'params' => $p, 'active' => 'church-admin.campaigns.*', 'show' => $can('campaigns.manage')],
        ]],
        ['heading' => __('People'), 'items' => [
            ['label' => __('Members'), 'icon' => 'bi-people', 'route' => 'church-admin.members.index', 'params' => $p, 'active' => 'church-admin.members.*', 'show' => $can('members.manage')],
            ['label' => __('Small groups'), 'icon' => 'bi-diagram-3', 'route' => 'church-admin.small-groups.index', 'params' => $p, 'active' => 'church-admin.small-groups.*', 'show' => $can('groups.manage')],
            ['label' => __('Volunteers'), 'icon' => 'bi-hand-thumbs-up', 'route' => 'church-admin.volunteers.index', 'params' => $p, 'active' => 'church-admin.volunteers.*', 'show' => $can('groups.manage')],
            ['label' => __('Attendance'), 'icon' => 'bi-qr-code-scan', 'route' => 'church-admin.attendance.index', 'params' => $p, 'active' => 'church-admin.attendance.*', 'show' => $can('members.manage')],
            ['label' => __('Pastoral care'), 'icon' => 'bi-heart-pulse', 'route' => 'church-admin.care.index', 'params' => $p, 'active' => ['church-admin.care.*', 'church-admin.counseling.*'], 'show' => $can('care.manage')],
        ]],
        ['heading' => __('Community'), 'items' => [
            ['label' => __('Prayer wall'), 'icon' => 'bi-chat-heart', 'route' => 'church-admin.prayer.index', 'params' => $p, 'active' => 'church-admin.prayer.*', 'show' => $can('prayer.moderate')],
            ['label' => __('Testimonies'), 'icon' => 'bi-stars', 'route' => 'church-admin.testimonies.index', 'params' => $p, 'active' => 'church-admin.testimonies.*', 'show' => $can('community.moderate')],
            ['label' => __('Comments'), 'icon' => 'bi-chat-left-dots', 'route' => 'church-admin.comments.index', 'params' => $p, 'active' => 'church-admin.comments.*', 'show' => $can('community.moderate')],
            ['label' => __('Send notification'), 'icon' => 'bi-send', 'route' => 'church-admin.notifications.create', 'params' => $p, 'active' => 'church-admin.notifications.*', 'show' => $can('notifications.send')],
        ]],
        ['heading' => __('Settings'), 'items' => [
            ['label' => __('Church profile'), 'icon' => 'bi-building', 'route' => 'church-admin.settings.profile', 'params' => $p, 'active' => ['church-admin.settings.profile', 'church-admin.branches.*', 'church-admin.setup*'], 'show' => $can('settings.manage')],
            ['label' => __('Staff & roles'), 'icon' => 'bi-person-badge', 'route' => 'church-admin.roles.index', 'params' => $p, 'active' => ['church-admin.roles.*', 'church-admin.staff.*'], 'show' => $can('roles.manage')],
            ['label' => __('Giving channels'), 'icon' => 'bi-wallet2', 'route' => 'church-admin.settings.giving', 'params' => $p, 'show' => $can('settings.manage')],
            ['label' => __('Website & branding'), 'icon' => 'bi-palette', 'route' => 'church-admin.settings.branding', 'params' => $p, 'active' => ['church-admin.settings.branding', 'church-admin.settings.website'], 'show' => $can('settings.manage')],
            ['label' => __('Integrations'), 'icon' => 'bi-plug', 'route' => 'church-admin.settings.integrations', 'params' => $p, 'show' => $can('settings.manage')],
            ['label' => __('Billing & plan'), 'icon' => 'bi-receipt', 'route' => 'church-admin.billing.index', 'params' => $p, 'active' => 'church-admin.billing.*', 'show' => $can('settings.manage')],
            ['label' => __('Support'), 'icon' => 'bi-life-preserver', 'route' => 'church-admin.support.index', 'params' => $p, 'active' => 'church-admin.support.*'],
            ['label' => __('Audit log'), 'icon' => 'bi-clipboard-data', 'route' => 'church-admin.audit-logs', 'params' => $p, 'show' => $can('roles.manage')],
        ]],
    ];
@endphp
<x-layouts.app-shell :title="$title ? $title.' · '.$church->name : $church->name" :nav="$nav" area="church-admin"
    :search-action="Route::has('church-admin.search') ? route('church-admin.search', $church) : null"
    :search-placeholder="__('Search members, sermons, donations…')">
    <x-slot:sidebarTop>
        <div class="dropdown ff-church-switcher">
            <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <x-ff.church-avatar :church="$church" size="sm" />
                <span class="min-w-0">
                    <span class="d-block fw-semibold text-ff-black text-truncate small">{{ $church->name }}</span>
                    <span class="d-block small-xs text-ff-muted">{{ __('Church admin') }}</span>
                </span>
            </button>
            <ul class="dropdown-menu">
                @foreach ($staffChurches ?? [] as $other)
                    <li><a @class(['dropdown-item', 'active' => $other->id === $church->id]) href="{{ route('church-admin.dashboard', $other) }}">{{ $other->name }}</a></li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('churches.show', $church) }}" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>{{ __('View public page') }}</a></li>
                <li><a class="dropdown-item" href="{{ ff_church_url($church) }}" target="_blank"><i class="bi bi-globe me-2"></i>{{ __('Open church website') }}</a></li>
            </ul>
        </div>
    </x-slot:sidebarTop>
    @if ($church->status !== 'active')
        <div class="alert alert-warning d-flex align-items-center gap-2 rounded-4">
            <i class="bi bi-hourglass-split"></i>
            <div>
                @if ($church->status === 'pending')
                    {{ __('Your church is awaiting approval by the FaithFellows team. You can finish setting up your profile in the meantime.') }}
                @else
                    {{ __('This church is currently :status. Public pages and giving are disabled.', ['status' => $church->status]) }}
                @endif
            </div>
        </div>
    @endif
    {{ $slot }}
</x-layouts.app-shell>
