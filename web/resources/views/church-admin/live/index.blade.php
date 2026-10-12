<x-layouts.church-admin :church="$church" :title="__('Live streams')">
    <x-ff.page-header :title="__('Live streams')" :subtitle="__('Schedule services, go live from OBS/Larix or YouTube, and archive recordings as sermons.')">
        <a href="{{ route('church-admin.live.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-broadcast"></i> {{ __('Schedule a service') }}</a>
    </x-ff.page-header>

    <div class="ff-card ff-card-sm mb-4 d-flex flex-wrap align-items-center gap-3">
        <i class="bi bi-hourglass-split text-ff-primary fs-4"></i>
        <div class="flex-grow-1" style="min-width: 220px">
            <div class="small fw-semibold text-ff-black">{{ __('Stream hours this month: :used of :limit', ['used' => $usage['used'], 'limit' => $usage['limit']]) }}</div>
            <x-ff.progress :value="$usage['limit'] ? $usage['used'] / $usage['limit'] * 100 : 0" size="sm" />
        </div>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Service') }}</th><th>{{ __('Scheduled') }}</th><th>{{ __('Source') }}</th><th>{{ __('Peak viewers') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($streams as $stream)
                        <tr>
                            <td><a href="{{ route('church-admin.live.show', [$church, $stream]) }}" class="fw-semibold text-ff-black">{{ $stream->title }}</a></td>
                            <td class="small text-nowrap">{{ $stream->scheduled_at?->format('D j M, g:i A') }}</td>
                            <td><span class="ff-badge">{{ strtoupper($stream->source_type) }}</span></td>
                            <td>{{ number_format($stream->peak_viewers) }}</td>
                            <td>@if ($stream->isLive())<span class="ff-badge-live">{{ __('Live') }}</span>@else<x-ff.status :status="$stream->status" />@endif
                                @if ($stream->sermon)<a href="{{ route('sermons.show', $stream->sermon) }}" class="small ms-1">{{ __('sermon') }}</a>@endif</td>
                            <td class="text-end"><a href="{{ route('church-admin.live.show', [$church, $stream]) }}" class="btn btn-sm btn-ff-soft">{{ __('Control room') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-broadcast" :title="__('No streams yet')" :message="__('Schedule your Sunday service and share the link with your members.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($streams->hasPages())<div class="ff-table-footer">{{ $streams->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
