<x-layouts.church-admin :church="$church" :title="__('Prayer wall')">
    <x-ff.page-header :title="__('Prayer wall')" :subtitle="__('Approve requests before they go out to the whole church, and reply privately to requests sent only to the pastors.')" />

    <nav class="ff-tabs mb-4" aria-label="{{ __('Prayer requests') }}">
        @foreach (['pending' => [__('To approve'), 'bi-hourglass-split'], 'private' => [__('Private to pastors'), 'bi-lock'], 'wall' => [__('On the wall'), 'bi-chat-heart'], 'declined' => [__('Kept private'), 'bi-eye-slash'], 'hidden' => [__('Hidden'), 'bi-slash-circle']] as $key => [$label, $icon])
            <a href="{{ route('church-admin.prayer.index', [$church, 'status' => $key]) }}" @class(['ff-tab', 'active' => $status === $key])>
                <i class="bi {{ $icon }}"></i> {{ $label }}
                @if (($counts[$key] ?? 0) > 0)<span @class(['ff-badge ms-1', 'ff-badge-primary' => in_array($key, ['pending', 'private'], true)])>{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="d-grid gap-3" style="max-width: 960px">
        @forelse ($prayers as $p)
            <article class="ff-card">
                <div class="ff-feed-meta mb-2">
                    <x-ff.avatar :user="$p->user" size="sm" />
                    <div class="min-w-0">
                        <strong class="text-ff-black">{{ $p->user?->name ?? __('Deleted user') }}</strong>
                        @if ($p->is_anonymous)<span class="small-xs text-ff-muted"><i class="bi bi-incognito"></i> {{ __('anonymous to members') }}</span>@endif
                        <div class="small-xs text-ff-muted">{{ $p->created_at->format('D j M Y, H:i') }} · {{ $p->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="ff-feed-type d-flex gap-1 flex-wrap justify-content-end">
                        <span class="ff-badge @if ($p->visibility === 'pastor') ff-badge-dark @endif">{{ ['public' => __('Everyone'), 'church' => __('Church family'), 'pastor' => __('Pastors only')][$p->visibility] }}</span>
                        <x-ff.status :status="$p->status === 'pending' ? 'pending' : $p->status">{{ ['pending' => __('Awaiting approval'), 'open' => __('Open'), 'answered' => __('Answered'), 'declined' => __('Kept private'), 'hidden' => __('Hidden')][$p->status] ?? $p->status }}</x-ff.status>
                    </span>
                </div>
                @if ($p->title)<h2 class="h6 mb-1">{{ $p->title }}</h2>@endif
                <p class="mb-2" style="white-space: pre-line">{{ $p->body }}</p>
                @if ($p->status !== 'pending' && $p->visibility !== 'pastor')
                    <div class="small text-ff-muted mb-2"><span aria-hidden="true">🙏</span> {{ trans_choice(':count person prayed|:count people prayed', $p->prayed_count) }} @if ($p->chain_enabled)· <i class="bi bi-link-45deg"></i> {{ __('prayer chain') }}@endif</div>
                @endif

                @if ($p->pastor_response)
                    <div class="ff-summary-card mb-2"><div class="small fw-semibold text-ff-primary mb-1"><i class="bi bi-chat-quote"></i> {{ __('Reply from :name · :when', ['name' => $p->responder?->name ?? __('the pastor'), 'when' => $p->responded_at?->diffForHumans()]) }}</div><div class="ff-summary-text">{{ $p->pastor_response }}</div></div>
                @endif

                <div class="d-flex flex-wrap gap-2 align-items-start">
                    @if ($p->status === 'pending')
                        <form method="POST" action="{{ route('church-admin.prayer.approve', [$church, $p]) }}">@csrf
                            <button class="btn btn-ff-primary btn-sm"><i class="bi bi-megaphone"></i> {{ __('Approve & share with the church') }}</button>
                        </form>
                        <details class="d-inline-block">
                            <summary class="btn btn-ff-soft btn-sm">{{ __('Keep private') }}</summary>
                            <form method="POST" action="{{ route('church-admin.prayer.decline', [$church, $p]) }}" class="mt-2 d-flex gap-2" style="min-width: min(420px, 80vw)">@csrf
                                <input name="note" class="form-control form-control-sm" maxlength="2000" placeholder="{{ __('Optional private note to them…') }}" aria-label="{{ __('Note') }}">
                                <button class="btn btn-sm btn-ff-soft text-nowrap">{{ __('Keep private') }}</button>
                            </form>
                        </details>
                    @endif

                    @if ($p->visibility === 'pastor' || $p->status === 'declined')
                        <form method="POST" action="{{ route('church-admin.prayer.respond', [$church, $p]) }}" class="flex-grow-1">@csrf
                            <div class="d-flex gap-2">
                                <textarea name="response" rows="2" class="form-control form-control-sm" maxlength="3000" required placeholder="{{ $p->pastor_response ? __('Send another reply…') : __('Write a private reply — scripture, encouragement, an offer to visit…') }}" aria-label="{{ __('Reply') }}"></textarea>
                                <button class="btn btn-sm btn-ff-primary align-self-end text-nowrap"><i class="bi bi-send"></i> {{ __('Reply') }}</button>
                            </div>
                        </form>
                    @endif

                    <div class="ms-auto d-flex gap-1">
                        @if ($p->user && auth()->user()->canInChurch($church, 'care.manage'))
                            <a href="{{ route('church-admin.care.member', [$church, $p->user_id]) }}" class="btn btn-sm btn-ff-ghost" title="{{ __('Care record') }}"><i class="bi bi-heart-pulse"></i></a>
                        @endif
                        @if ($p->isShared() && $p->visibility !== 'pastor')
                            <form method="POST" action="{{ route('church-admin.prayer.update', [$church, $p]) }}">@csrf @method('PUT')
                                <button name="status" value="hidden" class="btn btn-sm btn-ff-ghost" title="{{ __('Hide from wall') }}"><i class="bi bi-eye-slash"></i></button>
                            </form>
                        @elseif ($p->status === 'hidden')
                            <form method="POST" action="{{ route('church-admin.prayer.update', [$church, $p]) }}">@csrf @method('PUT')
                                <button name="status" value="open" class="btn btn-sm btn-ff-soft">{{ __('Restore') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('church-admin.prayer.destroy', [$church, $p]) }}" data-ff-confirm="{{ __('Delete this request?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <x-ff.empty-state icon="bi-chat-heart" :title="$status === 'pending' ? __('Nothing waiting for approval') : __('No prayer requests here')" />
        @endforelse
    </div>
    <div class="mt-4">{{ $prayers->links() }}</div>
</x-layouts.church-admin>
