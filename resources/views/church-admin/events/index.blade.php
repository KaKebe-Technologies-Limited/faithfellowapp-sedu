<x-layouts.church-admin :church="$church" :title="__('Events')">
    <x-ff.page-header :title="__('Events')" :subtitle="__('Free and ticketed events with RSVPs, reminders and QR check-in.')">
        <a href="{{ route('church-admin.events.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New event') }}</a>
    </x-ff.page-header>

    <div class="ff-pills mb-3">
        <a href="{{ route('church-admin.events.index', [$church, 'when' => 'upcoming']) }}" @class(['ff-pill', 'active' => $when === 'upcoming'])>{{ __('Upcoming') }}</a>
        <a href="{{ route('church-admin.events.index', [$church, 'when' => 'past']) }}" @class(['ff-pill', 'active' => $when === 'past'])>{{ __('Past') }}</a>
        <a href="{{ route('church-admin.events.index', [$church, 'when' => 'all']) }}" @class(['ff-pill', 'active' => $when === 'all'])>{{ __('All') }}</a>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Event') }}</th><th>{{ __('When') }}</th><th>{{ __('Going') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                <a href="{{ route('church-admin.events.show', [$church, $event]) }}" class="fw-semibold text-ff-black">{{ $event->title }}</a>
                                <div class="small-xs text-ff-muted">{{ $event->is_online ? __('Online') : $event->location_name }} @if ($event->is_paid)· <span class="text-warning">{{ __('Ticketed') }}</span>@endif</div>
                            </td>
                            <td class="small text-nowrap">{{ $event->starts_at->format('D j M Y, g:i A') }}</td>
                            <td>{{ (int) $event->guests_sum }}@if ($event->capacity) / {{ $event->capacity }}@endif</td>
                            <td><x-ff.status :status="$event->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('church-admin.events.check-in', [$church, $event]) }}" class="btn btn-sm btn-ff-soft"><i class="bi bi-qr-code-scan"></i> {{ __('Check-in') }}</a>
                                <a href="{{ route('church-admin.events.edit', [$church, $event]) }}" class="btn btn-sm btn-ff-ghost">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ff.empty-state icon="bi-calendar-event" :title="__('No events')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())<div class="ff-table-footer">{{ $events->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
