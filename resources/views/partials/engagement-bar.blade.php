{{--
    Reactions + comments for any HasEngagement model.
    Expects $model and $type (key of EngagementController::TYPES). Optional: $open (show comments expanded).
    Eager-load `reactions` and `comments.user.media` on lists to avoid per-item queries.
--}}
@php
    $viewer = auth()->user();
    $reactionRows = $model->relationLoaded('reactions') ? $model->reactions : $model->reactions()->get(['id', 'user_id', 'type']);
    $reactionCounts = (object) $reactionRows->countBy('type')->all();
    $myReaction = $viewer ? $reactionRows->firstWhere('user_id', $viewer->id)?->type : null;
    $commentRows = ($model->relationLoaded('comments') ? $model->comments : $model->comments()->with('user.media')->oldest()->get())
        ->where('status', 'visible')->whereNull('parent_id');
    $commentsId = 'comments-'.$type.'-'.$model->getKey();
    $canModerate = $viewer?->canInChurch($model->church_id, 'community.moderate') ?? false;
@endphp
<div class="ff-engagement" x-data="reactions({ url: @js(route('reactions.toggle', [$type, $model->getKey()])), counts: @js($reactionCounts), mine: @js($myReaction), guest: @js(! $viewer), loginUrl: @js(route('login')) })">
    <div class="d-flex align-items-center gap-1 flex-wrap">
        @foreach (\App\Models\Reaction::TYPES as $key => $emoji)
            <button type="button" class="ff-reaction" :class="{ 'is-mine': mine === @js($key) }" @click="react(@js($key))"
                :aria-pressed="mine === @js($key)" aria-label="{{ __(ucfirst($key)) }}" title="{{ __(ucfirst($key)) }}">
                <span aria-hidden="true">{{ $emoji }}</span><span x-text="counts[@js($key)] || ''"></span>
            </button>
        @endforeach
        <button type="button" class="ff-reaction ms-auto" data-bs-toggle="collapse" data-bs-target="#{{ $commentsId }}" aria-expanded="{{ ($open ?? false) ? 'true' : 'false' }}" aria-controls="{{ $commentsId }}">
            <i class="bi bi-chat" aria-hidden="true"></i> {{ $commentRows->count() ?: '' }} <span class="visually-hidden">{{ __('comments') }}</span>
        </button>
    </div>

    <div @class(['collapse', 'show' => $open ?? false]) id="{{ $commentsId }}">
        <div class="pt-2">
            @foreach ($commentRows as $comment)
                <div class="ff-comment">
                    <x-ff.avatar :user="$comment->user" size="xs" />
                    <div class="ff-comment-body">
                        <div class="small-xs d-flex gap-2 align-items-center">
                            <strong class="text-ff-black">{{ $comment->user?->name ?? __('Member') }}</strong>
                            <span class="text-ff-muted">{{ $comment->created_at->diffForHumans() }}</span>
                            @if ($viewer && ($comment->user_id === $viewer->id || $canModerate))
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="ms-auto" data-ff-confirm="{{ __('Delete this comment?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm p-0 text-ff-muted" aria-label="{{ __('Delete comment') }}"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @endif
                        </div>
                        <div class="small text-ff-black" style="white-space: pre-line">{{ $comment->body }}</div>
                    </div>
                </div>
            @endforeach

            @auth
                <form method="POST" action="{{ route('comments.store', [$type, $model->getKey()]) }}" class="d-flex gap-2 mt-2">@csrf
                    <input name="body" class="form-control form-control-sm" maxlength="1000" required placeholder="{{ __('Write a kind word…') }}" aria-label="{{ __('Comment') }}">
                    <button class="btn btn-sm btn-ff-primary">{{ __('Post') }}</button>
                </form>
            @else
                <p class="small mb-0 mt-2"><a href="{{ route('login') }}">{{ __('Sign in') }}</a> {{ __('to join the conversation.') }}</p>
            @endauth
        </div>
    </div>
</div>
