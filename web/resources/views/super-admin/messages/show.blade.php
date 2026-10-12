<x-layouts.super-admin :title="$message->subject ?: __('Message')">
    <x-ff.page-header :title="$message->subject ?: __('(no subject)')" :breadcrumbs="[__('Contact inbox') => route('super-admin.messages.index'), $message->name => null]">
        <form method="POST" action="{{ route('super-admin.messages.destroy', $message) }}" data-ff-confirm="{{ __('Delete this message?') }}">@csrf @method('DELETE')
            <button class="btn btn-ff-ghost text-danger"><i class="bi bi-trash"></i> {{ __('Delete') }}</button>
        </form>
    </x-ff.page-header>

    <div style="max-width: 820px" class="d-grid gap-4">
        <x-ff.card>
            <div class="d-flex flex-wrap gap-3 small text-ff-muted mb-3">
                <span><i class="bi bi-person"></i> {{ $message->name }}</span>
                <a href="mailto:{{ $message->email }}"><i class="bi bi-envelope"></i> {{ $message->email }}</a>
                @if ($message->phone)<a href="tel:{{ $message->phone }}"><i class="bi bi-telephone"></i> {{ $message->phone }}</a>@endif
                <span><i class="bi bi-clock"></i> {{ $message->created_at->format('j M Y, H:i') }}</span>
            </div>
            <div class="text-ff-black" style="white-space: pre-line">{{ $message->message }}</div>
        </x-ff.card>

        <form method="POST" action="{{ route('super-admin.messages.reply', $message) }}" class="ff-card">@csrf
            <x-ff.textarea name="body" :label="__('Reply by email')" rows="7" required maxlength="5000"
                :help="__('Sent from :from with your name in the signature.', ['from' => config('mail.from.address')])" />
            <button class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Send reply') }}</button>
            @if ($message->status === 'replied')<span class="small text-ff-muted ms-2">{{ __('Already replied once.') }}</span>@endif
        </form>
    </div>
</x-layouts.super-admin>
