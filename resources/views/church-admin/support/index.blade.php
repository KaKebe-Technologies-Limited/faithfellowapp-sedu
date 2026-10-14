<x-layouts.church-admin :church="$church" :title="__('Support')">
    <x-ff.page-header :title="__('Support')" :subtitle="__('Questions, problems or ideas? The Kakebe Technologies team is here to help.')">
        <a href="{{ route('church-admin.support.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New ticket') }}</a>
    </x-ff.page-header>

    @php
        $email = rescue(fn () => \App\Models\PlatformSetting::get('support_email', config('faithfellows.company.email')), config('faithfellows.company.email'), false);
        $phone = rescue(fn () => \App\Models\PlatformSetting::get('support_phone', config('faithfellows.company.phone')), config('faithfellows.company.phone'), false);
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="ff-card ff-card-sm d-flex gap-3 align-items-center h-100"><i class="bi bi-envelope fs-4 text-ff-primary"></i><div><div class="small text-ff-muted">{{ __('Email') }}</div><a href="mailto:{{ $email }}" class="fw-semibold">{{ $email }}</a></div></div></div>
        <div class="col-md-4"><div class="ff-card ff-card-sm d-flex gap-3 align-items-center h-100"><i class="bi bi-telephone fs-4 text-ff-primary"></i><div><div class="small text-ff-muted">{{ __('Phone') }}</div><a href="tel:{{ $phone }}" class="fw-semibold">{{ $phone }}</a></div></div></div>
        <div class="col-md-4"><div class="ff-card ff-card-sm d-flex gap-3 align-items-center h-100"><i class="bi bi-clock fs-4 text-ff-primary"></i><div><div class="small text-ff-muted">{{ __('Response time') }}</div><span class="fw-semibold">{{ __('Within one working day') }}</span></div></div></div>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('Category') }}</th><th>{{ __('Opened by') }}</th><th>{{ __('Last update') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $t)
                        <tr>
                            <td><a href="{{ route('church-admin.support.show', [$church, $t]) }}" class="fw-semibold text-ff-black">{{ $t->subject }}</a><div class="small-xs text-ff-muted">{{ $t->reference }} · {{ trans_choice(':count message|:count messages', $t->replies_count) }}</div></td>
                            <td class="small">{{ __(\App\Models\SupportTicket::CATEGORIES[$t->category]) }}</td>
                            <td class="small">{{ $t->user?->name }}</td>
                            <td class="small">{{ $t->last_reply_at?->diffForHumans() }}</td>
                            <td><x-ff.status :status="$t->status">{{ $t->status === 'pending' ? __('Awaiting your reply') : null }}</x-ff.status></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ff.empty-state icon="bi-life-preserver" :title="__('No tickets yet')" :message="__('Open a ticket and we will get back to you.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages())<div class="ff-table-footer">{{ $tickets->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
