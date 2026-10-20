@props(['title' => null])
@php
    $nav = [
        ['heading' => null, 'items' => [
            ['label' => __('Dashboard'), 'icon' => 'bi-speedometer2', 'route' => 'super-admin.dashboard'],
            ['label' => __('Analytics'), 'icon' => 'bi-graph-up-arrow', 'route' => 'super-admin.analytics'],
        ]],
        ['heading' => __('Tenants'), 'items' => [
            ['label' => __('Churches'), 'icon' => 'bi-buildings', 'route' => 'super-admin.churches.index', 'active' => 'super-admin.churches.*', 'badge' => ($pendingChurches ?? 0) ?: null],
            ['label' => __('Subscribers'), 'icon' => 'bi-people-fill', 'route' => 'super-admin.subscribers.index', 'active' => 'super-admin.subscribers.*'],
            ['label' => __('Subscriptions'), 'icon' => 'bi-arrow-repeat', 'route' => 'super-admin.subscriptions.index', 'active' => 'super-admin.subscriptions.*'],
            ['label' => __('Invoices'), 'icon' => 'bi-receipt', 'route' => 'super-admin.invoices.index', 'active' => 'super-admin.invoices.*'],
            ['label' => __('Plans'), 'icon' => 'bi-box-seam', 'route' => 'super-admin.plans.index', 'active' => 'super-admin.plans.*'],
            ['label' => __('Users'), 'icon' => 'bi-people', 'route' => 'super-admin.users.index', 'active' => 'super-admin.users.*'],
        ]],
        ['heading' => __('Operations'), 'items' => [
            ['label' => __('Support tickets'), 'icon' => 'bi-life-preserver', 'route' => 'super-admin.support.index', 'active' => 'super-admin.support.*', 'badge' => ($openTickets ?? 0) ?: null],
            ['label' => __('Moderation'), 'icon' => 'bi-shield-exclamation', 'route' => 'super-admin.moderation.index', 'active' => 'super-admin.moderation.*'],
            ['label' => __('Podcasts'), 'icon' => 'bi-broadcast-pin', 'route' => 'super-admin.podcasts.index', 'active' => 'super-admin.podcasts.*', 'badge' => \App\Models\Podcast::where('status', 'pending')->count() ?: null],
            ['label' => __('Hymn book'), 'icon' => 'bi-journal-richtext', 'route' => 'super-admin.hymnals.index', 'active' => 'super-admin.hymnals.*'],
            ['label' => __('App features'), 'icon' => 'bi-toggles', 'route' => 'super-admin.features.index', 'active' => 'super-admin.features.*'],
            ['label' => __('Inspiration'), 'icon' => 'bi-stars', 'route' => 'super-admin.inspiration.index', 'active' => 'super-admin.inspiration.*'],
            ['label' => __('Email'), 'icon' => 'bi-envelope-at', 'route' => 'super-admin.email.edit', 'active' => 'super-admin.email.*'],
            ['label' => __('Push test'), 'icon' => 'bi-bell', 'route' => 'super-admin.push.index', 'active' => 'super-admin.push.*'],
            ['label' => __('Google sign-in'), 'icon' => 'bi-google', 'route' => 'super-admin.google.edit', 'active' => 'super-admin.google.*'],
            ['label' => __('Radio'), 'icon' => 'bi-music-note-beamed', 'route' => 'super-admin.radio.index', 'active' => 'super-admin.radio.*'],
            ['label' => __('Blog'), 'icon' => 'bi-journal-richtext', 'route' => 'super-admin.blog.index', 'active' => 'super-admin.blog.*'],
            ['label' => __('Contact inbox'), 'icon' => 'bi-inbox', 'route' => 'super-admin.messages.index', 'active' => 'super-admin.messages.*'],
            ['label' => __('Audit logs'), 'icon' => 'bi-clipboard-data', 'route' => 'super-admin.audit-logs', 'active' => 'super-admin.audit-logs*'],
        ]],
        ['heading' => __('Platform'), 'items' => [
            ['label' => __('Settings'), 'icon' => 'bi-sliders', 'route' => 'super-admin.settings', 'active' => 'super-admin.settings'],
            ['label' => __('Payment gateways'), 'icon' => 'bi-credit-card-2-front', 'route' => 'super-admin.settings.gateways'],
            ['label' => __('Database updates'), 'icon' => 'bi-database', 'route' => 'super-admin.updates.index', 'active' => 'super-admin.updates.*', 'badge' => count(\App\Support\DatabaseUpdates::pending()) ?: null],
        ]],
    ];
@endphp
<x-layouts.app-shell :title="$title" :nav="$nav" area="super-admin">
    <x-slot:topbarStart>
        <span class="ff-badge ff-badge-dark"><i class="bi bi-shield-lock"></i> {{ __('Super Admin') }}</span>
    </x-slot:topbarStart>
    {{ $slot }}
</x-layouts.app-shell>
