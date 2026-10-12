<x-layouts.super-admin :title="$ticket->subject">
    <x-ff.page-header :title="$ticket->subject" :subtitle="$ticket->reference.' · '.__(\App\Models\SupportTicket::CATEGORIES[$ticket->category])"
        :breadcrumbs="[__('Support tickets') => route('super-admin.support.index'), $ticket->reference => null]" />

    <div class="row g-4">
        <div class="col-lg-8">
            @include('partials.ticket-thread')

            <form method="POST" action="{{ route('super-admin.support.reply', $ticket) }}" class="ff-card mt-4">@csrf
                <x-ff.textarea name="body" :label="__('Reply')" rows="5" required maxlength="5000" />
                <div class="d-flex align-items-center gap-3">
                    <x-ff.checkbox name="is_internal" :label="__('Internal note (the church won’t see it)')" switch />
                    <button class="btn btn-ff-primary ms-auto"><i class="bi bi-send"></i> {{ __('Send') }}</button>
                </div>
            </form>
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <form method="POST" action="{{ route('super-admin.support.update', $ticket) }}" class="ff-card">@csrf @method('PATCH')
                <x-ff.select name="status" :label="__('Status')" :options="['open' => __('Open'), 'pending' => __('Waiting on church'), 'resolved' => __('Resolved'), 'closed' => __('Closed')]" :value="$ticket->status" />
                <x-ff.select name="priority" :label="__('Priority')" :options="collect(\App\Models\SupportTicket::PRIORITIES)->map(fn ($l) => __($l))" :value="$ticket->priority" />
                <x-ff.select name="assigned_to" :label="__('Assigned to')" :options="$agents" :value="$ticket->assigned_to" :placeholder="__('— Unassigned —')" />
                <button class="btn btn-ff-soft w-100">{{ __('Update ticket') }}</button>
            </form>

            <x-ff.card :title="__('Church')">
                @if ($ticket->church)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <x-ff.church-avatar :church="$ticket->church" size="sm" />
                        <a href="{{ route('super-admin.churches.show', $ticket->church) }}" class="fw-semibold">{{ $ticket->church->name }}</a>
                    </div>
                    <div class="small text-ff-muted">{{ $ticket->church->city }} · <x-ff.status :status="$ticket->church->billing_status ?? 'active'" /></div>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('Not linked to a church.') }}</p>
                @endif
                <hr>
                <div class="small"><strong>{{ $ticket->user?->name }}</strong><br>{{ $ticket->user?->email }}<br>{{ $ticket->user?->phone }}</div>
                @if ($ticket->user && ! $ticket->user->isSuperAdmin())
                    <form method="POST" action="{{ route('super-admin.impersonate.start', $ticket->user) }}" class="mt-2" data-ff-confirm="{{ __('Log in as this user to reproduce the issue? This is recorded in the audit log.') }}">@csrf
                        <button class="btn btn-sm btn-ff-ghost"><i class="bi bi-person-badge"></i> {{ __('Impersonate') }}</button>
                    </form>
                @endif
            </x-ff.card>

            @if ($history->isNotEmpty())
                <x-ff.card :title="__('Earlier tickets')">
                    @foreach ($history as $h)
                        <a href="{{ route('super-admin.support.show', $h) }}" class="d-flex justify-content-between small py-1"><span class="text-truncate">{{ $h->subject }}</span><x-ff.status :status="$h->status" /></a>
                    @endforeach
                </x-ff.card>
            @endif
        </aside>
    </div>
</x-layouts.super-admin>
