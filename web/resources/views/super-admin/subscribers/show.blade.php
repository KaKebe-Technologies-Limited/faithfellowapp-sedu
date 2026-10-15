<x-layouts.super-admin :title="__('Subscribers of :church', ['church' => $church->name])">
    <x-ff.page-header :title="$church->name" :subtitle="__('Subscribers')" :breadcrumbs="[__('Subscribers') => route('super-admin.subscribers.index'), $church->name => null]">
        <a href="{{ route('super-admin.churches.show', $church) }}" class="btn btn-ff-ghost"><i class="bi bi-buildings"></i> {{ __('Church') }}</a>
    </x-ff.page-header>

    @if ($church->dormant_warned_at || $church->dormant_blocked_at)
        <div class="alert alert-warning small">
            @if ($church->dormant_blocked_at)
                {{ __('This church was blocked on :d for staying quiet. Open it again from the church page when it is ready to start.', ['d' => $church->dormant_blocked_at->format('j F Y')]) }}
            @else
                {{ __('This church was warned on :d for being quiet. It is blocked :n days after the warning if nothing happens.', ['d' => $church->dormant_warned_at->format('j F Y'), 'n' => app(\App\Services\DormancyService::class)->blockAfterDays()]) }}
            @endif
        </div>
    @endif

    @include('partials.subscriber-stats', ['stats' => $stats])
</x-layouts.super-admin>
