<x-layouts.member :title="__('Campaigns')">
    <x-ff.page-header :title="__('Campaigns')" :subtitle="__('Building projects, missions and welfare drives from your churches.')" />

    @if ($campaigns->isEmpty())
        <x-ff.empty-state icon="bi-flag" :title="__('No active campaigns')" :message="__('When your churches launch fundraising campaigns, they will appear here.')" />
    @else
        <div class="row g-4">
            @foreach ($campaigns as $campaign)
                <div class="col-md-6 col-xl-4">@include('member.campaigns._card')</div>
            @endforeach
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif
</x-layouts.member>
