<x-layouts.member :title="__('Small groups')">
    <x-ff.page-header :title="__('Small groups')" :subtitle="__('Cells, fellowships and Bible studies near you — do life together.')" />

    <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('My groups') }}</h2>
    @if ($mine->isEmpty())
        <p class="text-ff-muted mb-4">{{ __('You are not in a group yet. Find one below.') }}</p>
    @else
        <div class="row g-3 mb-4">
            @foreach ($mine as $group)
                <div class="col-md-6 col-xl-4">@include('member.groups._card', ['pending' => in_array($group->id, $pending, true)])</div>
            @endforeach
        </div>
    @endif

    @if ($mapPoints->isNotEmpty())
        <div class="ff-card ff-card-flush mb-4 overflow-hidden">
            <div data-ff-map='@json($mapPoints)' style="height: 320px" role="region" aria-label="{{ __('Map of small groups') }}"></div>
        </div>
    @endif

    <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('Find a group') }}</h2>
    @if ($discover->isEmpty())
        <x-ff.empty-state icon="bi-people" :title="__('No open groups right now')" :message="__('Your church will list small groups here as they open.')" />
    @else
        <div class="row g-3">
            @foreach ($discover as $group)
                <div class="col-md-6 col-xl-4">@include('member.groups._card', ['joinable' => true, 'pending' => in_array($group->id, $pending, true)])</div>
            @endforeach
        </div>
    @endif
</x-layouts.member>
