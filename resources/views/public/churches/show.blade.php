@php
    $layout = auth()->check() ? 'layouts.member' : 'layouts.public';
    $tabs = collect([
        'home' => __('Home'),
        'sermons' => __('Sermons'),
        'events' => __('Events'),
        'campaigns' => __('Campaigns'),
        'prayer' => __('Prayer wall'),
        'contact' => __('Contact'),
    ])->filter(fn ($label, $key) => in_array($key, ['home', 'contact'], true) || array_key_exists($key, $sections));
    $tab = $tabs->has($tab) ? $tab : 'home';
@endphp
<x-dynamic-component :component="$layout" :title="$church->name" :description="$church->description" :image="$church->cover_url">
    <div @class(['ff-church-page', 'mx-n3 mx-md-n4 mt-n3 mt-md-n4' => auth()->check()])>
        <div class="ff-church-cover" style="{{ $church->cover_url ? "background-image:url('".e($church->cover_url)."')" : 'background: linear-gradient(135deg, '.e($church->brand_color).' 0%, #F27EA8 100%)' }}"></div>
        <div class="container-xxl px-3 px-md-4">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end mb-4">
                <div class="ff-church-logo">
                    @if ($church->logo_url)<img src="{{ $church->logo_url }}" alt="{{ $church->name }}">@else{{ ff_initials($church->name) }}@endif
                </div>
                <div class="flex-grow-1 min-w-0">
                    <h1 class="h2 mb-1">{{ $church->name }}</h1>
                    <div class="text-ff-muted small d-flex flex-wrap gap-3">
                        <span><i class="bi bi-geo-alt"></i> {{ $church->location }}</span>
                        @if ($church->denomination)<span><i class="bi bi-bookmark"></i> {{ $church->denomination }}</span>@endif
                        @if ($church->pastor_name)<span><i class="bi bi-person"></i> {{ $church->pastor_name }}</span>@endif
                        @isset($sections['subscriberCount'])<span><i class="bi bi-people"></i> {{ trans_choice(':count follower|:count followers', $sections['subscriberCount'], ['count' => ff_short_number($sections['subscriberCount'])]) }}</span>@endisset
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @includeIf('public.churches._subscribe-button', ['church' => $church, 'sections' => $sections])
                    @if (Route::has('give.create') && $church->isActive())
                        <a href="{{ route('give.create', $church) }}" class="btn btn-ff-outline"><i class="bi bi-heart"></i> {{ __('Give') }}</a>
                    @endif
                    @if ($sections['isStaff'])
                        <a href="{{ route('church-admin.dashboard', $church) }}" class="btn btn-ff-ghost"><i class="bi bi-gear"></i> {{ __('Manage') }}</a>
                    @endif
                </div>
            </div>

            @if (! $church->isActive())
                <div class="alert alert-warning rounded-4">{{ __('This church is :status and only visible to its staff.', ['status' => $church->status]) }}</div>
            @endif

            <nav class="ff-tabs mb-4" aria-label="{{ __('Church sections') }}">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('churches.show', [$church, 'tab' => $key]) }}" @class(['ff-tab', 'active' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="row g-4 pb-5">
                <div class="col-lg-8">
                    @if ($tab === 'home')
                        @include('public.churches.tabs.home')
                    @elseif ($tab === 'contact')
                        @include('public.churches.tabs.contact')
                    @else
                        @includeIf('public.churches.tabs.'.$tab)
                    @endif
                </div>
                <aside class="col-lg-4 d-grid gap-4 align-content-start">
                    @include('public.churches._sidebar')
                </aside>
            </div>
        </div>
    </div>
</x-dynamic-component>
