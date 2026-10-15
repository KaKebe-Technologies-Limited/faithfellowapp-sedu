<x-layouts.church-admin :church="$church" :title="__('Subscribers')">
    <x-ff.page-header :title="__('Subscribers')" :subtitle="__('Everyone who follows :church in the app: how many, how fast you are growing, who they are and how active they are.', ['church' => $church->name])">
        <a href="{{ route('church-admin.analytics.export', [$church, 'members']) }}" class="btn btn-ff-ghost"><i class="bi bi-filetype-csv"></i> CSV</a>
        @if (auth()->user()->canInChurch($church, 'members.manage'))
            <a href="{{ route('church-admin.members.index', $church) }}" class="btn btn-ff-primary"><i class="bi bi-people"></i> {{ __('See the members') }}</a>
        @endif
    </x-ff.page-header>

    @include('partials.subscriber-stats', ['stats' => $stats])
</x-layouts.church-admin>
