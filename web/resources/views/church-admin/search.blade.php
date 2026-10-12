<x-layouts.church-admin :church="$church" :title="__('Search')">
    <x-ff.page-header :title="$q ? __('Results for “:q”', ['q' => $q]) : __('Search')" />

    @php
        $labels = ['members' => __('Members'), 'sermons' => __('Sermons'), 'devotionals' => __('Devotionals'), 'announcements' => __('Announcements'), 'donations' => __('Donations'), 'events' => __('Events')];
        $editRoutes = ['sermons' => 'church-admin.sermons.edit', 'devotionals' => 'church-admin.devotionals.edit', 'announcements' => 'church-admin.announcements.edit', 'events' => 'church-admin.events.edit'];
    @endphp

    @if (empty($results))
        <x-ff.empty-state icon="bi-search" :title="__('Type at least two characters in the search bar')" />
    @else
        @foreach ($results as $key => $items)
            @continue($items->isEmpty())
            <h2 class="h6 text-uppercase text-ff-muted mt-4 mb-2">{{ $labels[$key] ?? Str::headline($key) }}</h2>
            <div class="ff-card ff-card-sm d-grid gap-1">
                @foreach ($items as $item)
                    <a href="{{ $key === 'members' ? route('church-admin.members.index', [$church, 'q' => $item->name]) : (isset($editRoutes[$key]) && Route::has($editRoutes[$key]) ? route($editRoutes[$key], [$church, $item]) : '#') }}" class="ff-nav-link mb-0">
                        <i class="bi {{ $key === 'members' ? 'bi-person' : 'bi-file-text' }}"></i>
                        <span>{{ $item->name ?? $item->title ?? $item->reference ?? '#'.$item->id }}</span>
                        @if ($key === 'members' && $item->phone)<span class="ms-auto small text-ff-muted">{{ $item->phone }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endforeach
    @endif
</x-layouts.church-admin>
