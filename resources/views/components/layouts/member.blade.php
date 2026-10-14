@props(['title' => null])
@php
    $nav = [
        ['heading' => null, 'items' => [
            ['label' => __('Home feed'), 'icon' => 'bi-house-heart', 'route' => 'dashboard'],
            ['label' => __('My churches'), 'icon' => 'bi-bookmark-heart', 'route' => 'my-churches.index', 'active' => 'my-churches.*'],
            ['label' => __('Discover churches'), 'icon' => 'bi-compass', 'route' => 'churches.index', 'active' => 'churches.*'],
        ]],
        ['heading' => __('Worship'), 'items' => [
            ['label' => __('Bible'), 'icon' => 'bi-book-half', 'route' => 'bible.index', 'active' => 'bible.*'],
            ['label' => __('My notes'), 'icon' => 'bi-journal-text', 'route' => 'notes.index', 'active' => 'notes.*'],
            ['label' => __('Sermons'), 'icon' => 'bi-mic', 'route' => 'sermons.index', 'active' => 'sermons.*'],
            ['label' => __('My podcasts'), 'icon' => 'bi-broadcast-pin', 'route' => 'podcasts.index', 'active' => 'podcasts.*'],
            ['label' => __('Live'), 'icon' => 'bi-broadcast', 'route' => 'live.index', 'active' => 'live.*', 'badge' => ($liveNowCount ?? 0) ?: null],
            ['label' => __('Devotionals'), 'icon' => 'bi-book', 'route' => 'devotionals.index', 'active' => 'devotionals.*'],
            ['label' => __('Reading plans'), 'icon' => 'bi-journal-check', 'route' => 'reading-plans.index', 'active' => ['reading-plans.*', 'reading-circles.*']],
            ['label' => __('Events'), 'icon' => 'bi-calendar-event', 'route' => 'events.index', 'active' => 'events.*'],
        ]],
        ['heading' => __('Give'), 'items' => [
            ['label' => __('Giving'), 'icon' => 'bi-heart', 'route' => 'giving.index', 'active' => ['giving.*', 'give.*', 'pledges.*', 'recurring.*']],
            ['label' => __('Campaigns'), 'icon' => 'bi-flag', 'route' => 'campaigns.index', 'active' => 'campaigns.*'],
        ]],
        ['heading' => __('Community'), 'items' => [
            ['label' => __('Prayer wall'), 'icon' => 'bi-chat-heart', 'route' => 'prayer.index', 'active' => 'prayer.*'],
            ['label' => __('Testimonies'), 'icon' => 'bi-stars', 'route' => 'testimonies.index', 'active' => 'testimonies.*'],
            ['label' => __('Small groups'), 'icon' => 'bi-people', 'route' => 'groups.index', 'active' => 'groups.*'],
            ['label' => __('Quizzes & games'), 'icon' => 'bi-controller', 'route' => 'games.index', 'active' => 'games.*', 'badge' => ($liveGamesCount ?? 0) ?: null],
            ['label' => __('Counseling'), 'icon' => 'bi-person-heart', 'route' => 'counseling.index', 'active' => 'counseling.*'],
            ['label' => __('Serving'), 'icon' => 'bi-hand-thumbs-up', 'route' => 'volunteering.index', 'active' => 'volunteering.*'],
            ['label' => __('Member directory'), 'icon' => 'bi-person-lines-fill', 'route' => 'directory.index', 'active' => 'directory.*'],
        ]],
        ['heading' => __('Account'), 'items' => [
            ['label' => __('Notifications'), 'icon' => 'bi-bell', 'route' => 'notifications.index', 'active' => 'notifications.*'],
            ['label' => __('Offline sermons'), 'icon' => 'bi-download', 'route' => 'offline'],
            ['label' => __('Profile & settings'), 'icon' => 'bi-person-gear', 'route' => 'profile.edit', 'active' => 'profile.*'],
        ]],
    ];
@endphp
<x-layouts.app-shell :title="$title" :nav="$nav" area="member"
    :search-action="Route::has('search') ? route('search') : null"
    :search-placeholder="__('Search sermons, events, churches…')">
    @isset($subscribedChurches)
        <x-slot:sidebarTop>
            @include('partials.church-switcher')
        </x-slot:sidebarTop>
    @endisset
    {{ $slot }}
</x-layouts.app-shell>
