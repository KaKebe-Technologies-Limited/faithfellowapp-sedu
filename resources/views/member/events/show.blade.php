@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="$event->title" :description="strip_tags((string) $event->description)" :image="$event->cover_url">
    <div @class(['container py-4 py-md-5' => ! auth()->check()])>
        @if ($event->cover_url)
            <img src="{{ $event->cover_url }}" alt="" class="ff-cover ff-rounded-xl mb-4" style="aspect-ratio: 21/9">
        @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if ($event->status === 'cancelled')<x-ff.status status="cancelled" />@endif
                    @if ($event->is_online)<span class="ff-badge ff-badge-info"><i class="bi bi-camera-video"></i> {{ __('Online') }}</span>@endif
                    @if ($event->is_paid)<span class="ff-badge ff-badge-warning">{{ __('Ticketed') }}</span>@endif
                    @if ($event->visibility === 'members')<span class="ff-badge">{{ __('Members only') }}</span>@endif
                </div>
                <h1 class="h2">{{ $event->title }}</h1>
                <a href="{{ route('churches.show', $event->church) }}" class="d-inline-flex align-items-center gap-2 mb-4 text-ff-black">
                    <x-ff.church-avatar :church="$event->church" size="xs" /> {{ $event->church->name }}
                </a>

                @if ($event->description)
                    <div class="ff-card mb-4"><div class="ff-prose" style="font-size:1rem">{!! ff_safe_html($event->description) !!}</div></div>
                @endif

                @if (! empty($event->agenda))
                    <x-ff.card :title="__('Agenda')" class="mb-4">
                        <ul class="list-unstyled mb-0 d-grid gap-2">
                            @foreach ($event->agenda as $item)
                                <li class="d-flex gap-3"><span class="fw-bold text-ff-primary" style="min-width: 70px">{{ $item['time'] ?? '' }}</span><span><strong class="text-ff-black">{{ $item['title'] }}</strong>@if (! empty($item['speaker']))<span class="text-ff-muted"> — {{ $item['speaker'] }}</span>@endif</span></li>
                            @endforeach
                        </ul>
                    </x-ff.card>
                @endif

                @if (! empty($event->speakers))
                    <h2 class="h5 mb-3">{{ __('Speakers') }}</h2>
                    <div class="row g-3 mb-4">
                        @foreach ($event->speakers as $speaker)
                            <div class="col-md-6"><div class="ff-card ff-card-sm d-flex gap-3">
                                <span class="ff-avatar ff-avatar-lg">{{ ff_initials($speaker['name']) }}</span>
                                <div><div class="fw-bold text-ff-black">{{ $speaker['name'] }}</div><div class="small text-ff-primary">{{ $speaker['role'] ?? '' }}</div><p class="small text-ff-muted mb-0">{{ $speaker['bio'] ?? '' }}</p></div>
                            </div></div>
                        @endforeach
                    </div>
                @endif

                @if ($event->qa_enabled)
                    <x-ff.card :title="__('Live Q&A')" class="mb-4">
                        @auth
                            <form method="POST" action="{{ route('events.questions.store', $event) }}" class="d-flex gap-2 mb-3">@csrf
                                <input name="body" class="form-control" maxlength="500" placeholder="{{ __('Ask the speakers a question…') }}" required aria-label="{{ __('Question') }}">
                                <button class="btn btn-ff-primary">{{ __('Ask') }}</button>
                            </form>
                        @endauth
                        @forelse ($event->questions as $q)
                            <div class="d-flex gap-3 py-2 border-bottom">
                                <form method="POST" action="{{ route('events.questions.upvote', [$event, $q]) }}">@csrf
                                    <button class="btn btn-sm btn-ff-soft" @guest disabled @endguest aria-label="{{ __('Upvote') }}"><i class="bi bi-hand-thumbs-up"></i> {{ $q->upvotes }}</button>
                                </form>
                                <div class="flex-grow-1"><div class="text-ff-black">{{ $q->body }}</div><div class="small-xs text-ff-muted">{{ $q->user?->first_name }} @if ($q->is_answered)· <span class="text-success">{{ __('Answered') }}</span>@endif</div></div>
                            </div>
                        @empty
                            <p class="small text-ff-muted mb-0">{{ __('No questions yet.') }}</p>
                        @endforelse
                    </x-ff.card>
                @endif

                @if ($event->recordingSermon)
                    <a href="{{ route('sermons.show', $event->recordingSermon) }}" class="btn btn-ff-outline"><i class="bi bi-play-circle"></i> {{ __('Watch the recording') }}</a>
                @endif
            </div>

            <aside class="col-lg-4 d-grid gap-4 align-content-start">
                <div class="ff-card">
                    <div class="d-flex gap-3 mb-3">
                        <div class="ff-date-tile"><div class="m">{{ $event->starts_at->format('M') }}</div><div class="d">{{ $event->starts_at->format('j') }}</div></div>
                        <div>
                            <div class="fw-bold text-ff-black">{{ $event->starts_at->format('l, j F Y') }}</div>
                            <div class="small text-ff-muted">{{ $event->starts_at->format('g:i A') }}@if ($event->ends_at) – {{ $event->ends_at->format($event->ends_at->isSameDay($event->starts_at) ? 'g:i A' : 'j M, g:i A') }}@endif</div>
                        </div>
                    </div>
                    @if (! $event->isPast() && $event->status === 'published')
                        <div class="small mb-3 text-ff-primary fw-semibold" x-data="countdown('{{ $event->starts_at->toIso8601String() }}')"><i class="bi bi-hourglass-split"></i> <span x-text="left"></span></div>
                    @endif
                    @if ($event->location_name || $event->address)
                        <p class="small mb-2"><i class="bi bi-geo-alt text-ff-primary"></i> {{ $event->location_name }}<br><span class="text-ff-muted">{{ $event->address }}</span></p>
                    @endif
                    @if ($event->is_online && $event->online_url && $rsvp?->status === 'going')
                        <a href="{{ $event->online_url }}" target="_blank" rel="noopener" class="btn btn-ff-soft btn-sm mb-2"><i class="bi bi-camera-video"></i> {{ __('Join online') }}</a>
                    @endif
                    <div class="small text-ff-muted mb-3"><i class="bi bi-people"></i> {{ trans_choice(':count person going|:count people going', $goingCount) }}@if ($seatsLeft !== null) · {{ __(':n seats left', ['n' => $seatsLeft]) }}@endif</div>

                    @if ($event->status !== 'published' || $event->isPast())
                        <div class="alert alert-light small mb-0">{{ $event->status === 'cancelled' ? __('This event was cancelled.') : __('This event has ended.') }}</div>
                    @elseif (! $event->rsvp_enabled)
                        <p class="small mb-0">{{ __('No registration needed — just come!') }}</p>
                    @elseif (! auth()->check())
                        <a href="{{ route('login') }}" class="btn btn-ff-primary w-100">{{ __('Sign in to RSVP') }}</a>
                    @elseif ($rsvp?->status === 'going')
                        <a href="{{ route('events.ticket', $event) }}" class="btn btn-ff-primary w-100 mb-2"><i class="bi bi-qr-code"></i> {{ __('Show my ticket') }}</a>
                        <form method="POST" action="{{ route('events.rsvp.cancel', $event) }}" data-ff-confirm="{{ __('Cancel your RSVP?') }}">@csrf @method('DELETE')
                            <button class="btn btn-ff-ghost w-100 btn-sm">{{ __('Cancel RSVP') }}</button>
                        </form>
                    @elseif ($rsvp?->status === 'pending_payment')
                        <div class="alert alert-warning small">{{ __('Your seats are reserved — complete payment to confirm.') }}</div>
                        @if ($canPay)<a href="{{ route('give.ticket', $rsvp) }}" class="btn btn-ff-primary w-100">{{ __('Pay :amount', ['amount' => ff_money($rsvp->amount)]) }}</a>@endif
                    @else
                        <form method="POST" action="{{ route('events.rsvp', $event) }}">@csrf
                            @if ($event->is_paid && $event->tickets->isNotEmpty())
                                <div class="d-grid gap-2 mb-3">
                                    @foreach ($event->tickets as $ticket)
                                        <label class="ff-method-option">
                                            <input type="radio" class="form-check-input mt-0" name="event_ticket_id" value="{{ $ticket->id }}" @checked($loop->first) @disabled(! $ticket->isOnSale())>
                                            <span class="flex-grow-1"><strong class="text-ff-black d-block">{{ $ticket->name }}</strong><span class="small text-ff-muted">{{ $ticket->isOnSale() ? ($ticket->remaining() !== null ? __(':n left', ['n' => $ticket->remaining()]) : __('Available')) : __('Sold out') }}</span></span>
                                            <strong class="text-ff-black">{{ $ticket->price > 0 ? ff_money($ticket->price, $ticket->currency) : __('Free') }}</strong>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <div class="d-flex gap-2 align-items-center mb-3">
                                <label for="quantity" class="small text-nowrap">{{ __('Guests') }}</label>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" class="form-control form-control-sm" style="max-width: 90px">
                            </div>
                            @error('quantity')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            @error('rsvp')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button class="btn btn-ff-primary w-100">{{ $event->is_paid ? __('Get tickets') : __('RSVP — I’m going') }}</button>
                            @unless ($event->is_paid)<button name="status" value="maybe" class="btn btn-ff-ghost w-100 btn-sm mt-1">{{ __('Maybe') }}</button>@endunless
                        </form>
                    @endif
                </div>
                @if ($event->lat && $event->lng)
                    <div class="ff-card ff-card-flush"><div style="height: 220px" data-ff-map="{{ json_encode([['lat' => $event->lat, 'lng' => $event->lng, 'label' => $event->location_name ?: $event->title]]) }}"></div></div>
                @endif
                <div class="ff-card"><x-ff.share :url="route('events.show', $event)" :title="$event->title" /></div>
            </aside>
        </div>
    </div>
</x-dynamic-component>
