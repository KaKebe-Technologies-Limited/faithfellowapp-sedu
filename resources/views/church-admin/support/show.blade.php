<x-layouts.church-admin :church="$church" :title="$ticket->subject">
    <x-ff.page-header :title="$ticket->subject" :subtitle="$ticket->reference.' · '.__(\App\Models\SupportTicket::CATEGORIES[$ticket->category])"
        :breadcrumbs="[__('Support') => route('church-admin.support.index', $church), $ticket->reference => null]">
        <x-ff.status :status="$ticket->status" class="align-self-center" />
        @unless ($ticket->isClosed())
            <form method="POST" action="{{ route('church-admin.support.close', [$church, $ticket]) }}" data-ff-confirm="{{ __('Close this ticket?') }}">@csrf
                <button class="btn btn-ff-ghost">{{ __('Mark as solved') }}</button>
            </form>
        @endunless
    </x-ff.page-header>

    <div style="max-width: 820px">
        @include('partials.ticket-thread')

        <form method="POST" action="{{ route('church-admin.support.reply', [$church, $ticket]) }}" class="ff-card mt-4">@csrf
            <x-ff.textarea name="body" :label="$ticket->isClosed() ? __('Reply to reopen this ticket') : __('Your reply')" rows="4" required maxlength="5000" />
            <button class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Send reply') }}</button>
        </form>
    </div>
</x-layouts.church-admin>
