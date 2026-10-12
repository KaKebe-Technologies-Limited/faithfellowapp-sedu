@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="$campaign->title" :description="$campaign->short_description" :image="$campaign->cover_url">
    @if ($celebrate)<span data-ff-celebrate hidden></span>@endif
    <div @class(['container py-4 py-md-5' => ! auth()->check()])>
        <div class="row g-4">
            <div class="col-lg-8">
                @if ($campaign->cover_url)
                    <img src="{{ $campaign->cover_url }}" alt="" class="ff-cover ff-rounded-xl mb-4">
                @endif
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="ff-tag">{{ $campaign->type_label }}</span>
                    @if ($campaign->goal_reached_at)<span class="ff-badge ff-badge-success">🎉 {{ __('Goal reached!') }}</span>@endif
                    @if ($campaign->status !== 'active')<x-ff.status :status="$campaign->status" />@endif
                </div>
                <h1 class="h2">{{ $campaign->title }}</h1>
                <a href="{{ route('churches.show', $campaign->church) }}" class="d-inline-flex align-items-center gap-2 text-ff-black mb-3"><x-ff.church-avatar :church="$campaign->church" size="xs" /> {{ $campaign->church->name }}</a>
                @if ($campaign->short_description)<p class="ff-lead">{{ $campaign->short_description }}</p>@endif

                {{-- Milestones --}}
                @if ($campaign->milestones->isNotEmpty())
                    <div class="ff-card mb-4">
                        <div class="row g-3">
                            @foreach ($campaign->milestones as $m)
                                <div class="col-6 col-md-3">
                                    <div @class(['ff-milestone', 'is-reached' => $m->reached_at])>
                                        <span class="ff-milestone-dot">{{ $m->reached_at ? '✓' : $m->percent.'%' }}</span>
                                        <span class="small"><strong class="d-block text-ff-black">{{ $m->percent }}%</strong>{{ ff_short_number($campaign->goal_amount * $m->percent / 100) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($campaign->story)
                    <div class="ff-card mb-4"><h2 class="h5">{{ __('Our story') }}</h2><div class="ff-prose" style="font-size:1rem">{!! ff_safe_html($campaign->story) !!}</div></div>
                @endif

                @php($gallery = $campaign->media->where('collection_name', 'gallery'))
                @if ($gallery->isNotEmpty())
                    <div class="row g-2 mb-4">
                        @foreach ($gallery->take(6) as $img)
                            <div class="col-4"><a href="{{ $img->getUrl() }}" target="_blank"><img src="{{ $img->getUrl() }}" alt="" class="w-100 rounded-3" style="aspect-ratio:1;object-fit:cover" loading="lazy"></a></div>
                        @endforeach
                    </div>
                @endif

                {{-- Updates --}}
                @if ($campaign->updates->isNotEmpty())
                    <h2 class="h5 mb-3">{{ __('Updates') }}</h2>
                    <div class="d-grid gap-3 mb-4">
                        @foreach ($campaign->updates as $update)
                            <div class="ff-card">
                                <div class="small text-ff-muted mb-1">{{ $update->published_at?->format('j M Y') }} @if ($update->is_testimony)<span class="ff-badge ff-badge-primary ms-1">{{ __('Testimony') }}</span>@endif</div>
                                <div class="fw-bold text-ff-black">{{ $update->title }}</div>
                                @if ($update->body)<div class="ff-prose mt-2" style="font-size:.95rem">{!! ff_safe_html($update->body) !!}</div>@endif
                                @if ($update->media->isNotEmpty())
                                    <div class="d-flex gap-2 mt-2 flex-wrap">
                                        @foreach ($update->media as $photo)<img src="{{ $photo->getUrl() }}" alt="" class="rounded-3" style="width:110px;height:110px;object-fit:cover" loading="lazy">@endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Comments --}}
                <x-ff.card :title="__('Words of encouragement')">
                    @auth
                        <form method="POST" action="{{ route('campaigns.comments.store', $campaign) }}" class="d-flex gap-2 mb-3">@csrf
                            <input name="body" class="form-control" maxlength="1000" placeholder="{{ __('Leave an encouraging word or prayer…') }}" required aria-label="{{ __('Comment') }}">
                            <button class="btn btn-ff-primary">{{ __('Post') }}</button>
                        </form>
                    @else
                        <p class="small"><a href="{{ route('login') }}">{{ __('Sign in') }}</a> {{ __('to leave a comment.') }}</p>
                    @endauth
                    @forelse ($campaign->comments as $comment)
                        <div class="d-flex gap-2 py-2 border-bottom">
                            <x-ff.avatar :user="$comment->user" size="sm" />
                            <div><div class="small"><strong class="text-ff-black">{{ $comment->user?->name }}</strong> <span class="text-ff-muted">· {{ $comment->created_at->diffForHumans() }}</span></div><div>{{ $comment->body }}</div></div>
                        </div>
                    @empty
                        <p class="small text-ff-muted mb-0">{{ __('Be the first to encourage this campaign.') }}</p>
                    @endforelse
                </x-ff.card>
            </div>

            <aside class="col-lg-4 d-grid gap-4 align-content-start">
                <div class="ff-card text-center position-sticky" style="top: 90px">
                    <x-ff.ring :value="$campaign->percent()" :size="160" :stroke="14" />
                    <div class="fs-3 fw-bold text-ff-black mt-3">{{ ff_money($campaign->raised_amount, $campaign->currency) }}</div>
                    <div class="small text-ff-muted mb-3">{{ __('raised of :goal goal', ['goal' => ff_money($campaign->goal_amount, $campaign->currency)]) }}</div>
                    <div class="row g-0 border-top border-bottom py-2 mb-3 small">
                        <div class="col"><strong class="d-block text-ff-black fs-5">{{ number_format($campaign->givers_count) }}</strong>{{ __('givers') }}</div>
                        <div class="col"><strong class="d-block text-ff-black fs-5">{{ ff_short_number($campaign->remaining()) }}</strong>{{ __('to go') }}</div>
                        @if ($campaign->daysLeft() !== null)<div class="col"><strong class="d-block text-ff-black fs-5">{{ $campaign->daysLeft() }}</strong>{{ __('days left') }}</div>@endif
                    </div>
                    @if ($campaign->pledged_amount > 0)<div class="small text-ff-muted mb-3">{{ __(':amount pledged', ['amount' => ff_money($campaign->pledged_amount)]) }}</div>@endif
                    @if ($campaign->isOpen())
                        <a href="{{ route('give.create', [$campaign->church, 'campaign' => $campaign->slug]) }}" class="btn btn-ff-primary btn-lg w-100 mb-2"><i class="bi bi-heart-fill"></i> {{ __('Give now') }}</a>
                        <a href="{{ route('give.create', [$campaign->church, 'campaign' => $campaign->slug]) }}#pledge" class="btn btn-ff-outline w-100">{{ __('Make a pledge') }}</a>
                    @endif
                    <hr>
                    <x-ff.share :url="$shareUrl" :title="$campaign->title" class="justify-content-center" />
                </div>

                @if ($campaign->teams->isNotEmpty())
                    <x-ff.card :title="__('Team challenge')">
                        @foreach ($campaign->teams->sortByDesc('raised_amount') as $team)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small"><strong style="color: {{ $team->color }}">{{ $team->name }}</strong><span>{{ ff_short_number($team->raised_amount) }}@if ($team->goal_amount) / {{ ff_short_number($team->goal_amount) }}@endif</span></div>
                                <div class="ff-progress ff-progress-sm"><div class="ff-progress-bar" style="width: {{ $team->goal_amount ? $team->percent() : min(100, $campaign->raised_amount > 0 ? $team->raised_amount / $campaign->raised_amount * 100 : 0) }}%; background: {{ $team->color }}"></div></div>
                            </div>
                        @endforeach
                    </x-ff.card>
                @endif

                @if ($leaderboard->isNotEmpty())
                    <x-ff.card :title="__('Top givers')" :subtitle="__('Only givers who chose to be listed')">
                        @foreach ($leaderboard as $i => $row)
                            <div class="ff-leaderboard-row"><span class="ff-leaderboard-rank">{{ $i + 1 }}</span><span class="flex-grow-1 small fw-semibold text-ff-black">{{ $row->name }}</span><span class="small">{{ ff_short_number($row->total) }}</span></div>
                        @endforeach
                    </x-ff.card>
                @endif

                @if ($recent->isNotEmpty())
                    <x-ff.card :title="__('Recent gifts')">
                        @foreach ($recent as $gift)
                            <div class="d-flex justify-content-between small py-1"><span>{{ $gift->display_name }}</span><span class="text-ff-muted">{{ $gift->paid_at?->diffForHumans(short: true) }}</span></div>
                        @endforeach
                    </x-ff.card>
                @endif

                <div class="ff-card text-center">
                    <h2 class="h6">{{ __('Give by QR code') }}</h2>
                    <div class="ff-qr mx-auto" style="max-width: 200px">{!! $qr !!}</div>
                    <a href="{{ route('campaigns.poster', $campaign) }}" target="_blank" class="small d-block mt-2"><i class="bi bi-printer"></i> {{ __('Print poster for events') }}</a>
                </div>
            </aside>
        </div>
    </div>
</x-dynamic-component>
