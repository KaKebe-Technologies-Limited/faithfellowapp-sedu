{{-- Support ticket conversation. Expects $ticket with replies.user loaded. --}}
<div class="d-grid gap-3">
    @foreach ($ticket->replies as $reply)
        <div @class(['ff-card ff-card-sm', 'ff-card-mist' => $reply->is_staff && ! $reply->is_internal, 'border-warning' => $reply->is_internal]) @if ($reply->is_internal) style="border-style: dashed" @endif>
            <div class="d-flex align-items-center gap-2 mb-2">
                <x-ff.avatar :user="$reply->user" size="sm" />
                <div class="small">
                    <strong class="text-ff-black">{{ $reply->user?->name ?? __('Deleted user') }}</strong>
                    @if ($reply->is_staff)<span class="ff-badge ff-badge-primary ms-1">{{ __('FaithFellows support') }}</span>@endif
                    @if ($reply->is_internal)<span class="ff-badge ff-badge-warning ms-1"><i class="bi bi-eye-slash"></i> {{ __('Internal note') }}</span>@endif
                    <div class="small-xs text-ff-muted">{{ $reply->created_at->format('j M Y, H:i') }}</div>
                </div>
            </div>
            <div style="white-space: pre-line">{{ $reply->body }}</div>
        </div>
    @endforeach
</div>
