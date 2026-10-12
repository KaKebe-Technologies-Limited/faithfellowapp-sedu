<x-layouts.church-admin :church="$church" :title="$event->title">
    <x-ff.page-header :title="$event->title" :subtitle="$event->starts_at->format('l j F Y, g:i A')"
        :breadcrumbs="[__('Events') => route('church-admin.events.index', $church), $event->title => null]">
        <a href="{{ route('events.show', $event) }}" target="_blank" class="btn btn-ff-ghost"><i class="bi bi-eye"></i> {{ __('Public page') }}</a>
        <a href="{{ route('church-admin.events.edit', [$church, $event]) }}" class="btn btn-ff-outline">{{ __('Edit') }}</a>
        <a href="{{ route('church-admin.events.check-in', [$church, $event]) }}" class="btn btn-ff-primary"><i class="bi bi-qr-code-scan"></i> {{ __('Door check-in') }}</a>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Going')" :value="$stats['going'].($event->capacity ? ' / '.$event->capacity : '')" icon="bi-people" primary /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Checked in')" :value="$stats['checked_in']" icon="bi-check2-square" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Awaiting payment')" :value="$stats['pending']" icon="bi-hourglass-split" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Ticket revenue')" :value="ff_money($stats['revenue'])" icon="bi-cash-coin" /></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar">
                    <h2 class="h6 mb-0">{{ __('Attendees') }}</h2>
                    <a href="{{ route('church-admin.events.export', [$church, $event]) }}" class="btn btn-sm btn-ff-soft ms-auto"><i class="bi bi-download"></i> CSV</a>
                </div>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Ticket') }}</th><th>{{ __('Guests') }}</th><th>{{ __('Status') }}</th><th>{{ __('Check-in') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rsvps as $r)
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><x-ff.avatar :user="$r->user" size="xs" /> {{ $r->user?->name }}</div></td>
                                    <td class="small">{{ $r->ticket?->name ?? '—' }}</td>
                                    <td>{{ $r->quantity }}</td>
                                    <td><x-ff.status :status="$r->status" /></td>
                                    <td class="small">
                                        @if ($r->checked_in_at)<span class="text-success"><i class="bi bi-check-circle-fill"></i> {{ $r->checked_in_at->format('g:i A') }}</span>
                                        @elseif ($r->status === 'going')
                                            <form method="POST" action="{{ route('church-admin.events.check-in.store', [$church, $event]) }}">@csrf<input type="hidden" name="code" value="{{ $r->code }}"><button class="btn btn-sm btn-ff-soft">{{ __('Check in') }}</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ff.empty-state icon="bi-people" :title="__('No RSVPs yet')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($rsvps->hasPages())<div class="ff-table-footer">{{ $rsvps->links() }}</div>@endif
            </div>
        </div>
        <div class="col-xl-4 d-grid gap-4 align-content-start">
            @if ($event->recording_url && ! $event->recording_sermon_id)
                <form method="POST" action="{{ route('church-admin.events.recording', [$church, $event]) }}" class="ff-card ff-card-mist">@csrf
                    <h2 class="h6">{{ __('Recording ready') }}</h2>
                    <p class="small">{{ __('Publish the event recording as a sermon so members can watch it later.') }}</p>
                    <button class="btn btn-ff-primary btn-sm">{{ __('Publish as sermon') }}</button>
                </form>
            @endif
            @if ($event->qa_enabled)
                <x-ff.card :title="__('Q&A')">
                    @forelse ($event->questions as $q)
                        <div @class(['py-2 border-bottom small', 'opacity-50' => $q->is_hidden])>
                            <div class="text-ff-black">{{ $q->body }}</div>
                            <div class="d-flex gap-2 mt-1 align-items-center">
                                <span class="text-ff-muted"><i class="bi bi-hand-thumbs-up"></i> {{ $q->upvotes }} · {{ $q->user?->first_name }}</span>
                                <form method="POST" action="{{ route('church-admin.events.questions.update', [$church, $event, $q]) }}" class="ms-auto">@csrf @method('PUT')
                                    <input type="hidden" name="is_answered" value="{{ $q->is_answered ? 0 : 1 }}"><button class="btn btn-sm btn-ff-ghost py-0">{{ $q->is_answered ? __('Unmark') : __('Answered') }}</button>
                                </form>
                                <form method="POST" action="{{ route('church-admin.events.questions.update', [$church, $event, $q]) }}">@csrf @method('PUT')
                                    <input type="hidden" name="is_hidden" value="{{ $q->is_hidden ? 0 : 1 }}"><button class="btn btn-sm btn-ff-ghost py-0 text-danger">{{ $q->is_hidden ? __('Show') : __('Hide') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="small text-ff-muted mb-0">{{ __('No questions yet.') }}</p>
                    @endforelse
                </x-ff.card>
            @endif
            <form method="POST" action="{{ route('church-admin.events.destroy', [$church, $event]) }}" data-ff-confirm="{{ __('Delete this event and all RSVPs?') }}">@csrf @method('DELETE')
                <button class="btn btn-ff-ghost text-danger w-100"><i class="bi bi-trash"></i> {{ __('Delete event') }}</button>
            </form>
        </div>
    </div>
</x-layouts.church-admin>
