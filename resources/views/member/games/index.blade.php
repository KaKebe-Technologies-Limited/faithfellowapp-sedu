<x-layouts.member :title="__('Quizzes & games')">
    <div class="ff-quiz-arena mb-4" style="min-height: auto">
        <div class="row g-4 align-items-center position-relative">
            <div class="col-lg-6">
                <span class="ff-quiz-chip mb-3"><i class="bi bi-controller"></i> {{ __('Fun & games') }}</span>
                <h1 class="display-6 fw-bold">{{ __('Play live quizzes with your church family') }}</h1>
                <p class="mb-0" style="opacity:.9">{{ __('When your church hosts a quiz, enter the game PIN shown on screen. Answer fast — quicker correct answers score more!') }}</p>
            </div>
            <div class="col-lg-6">
                <form method="POST" action="{{ route('games.join') }}" class="bg-white rounded-4 p-3 p-md-4 text-center shadow">@csrf
                    <label for="pin" class="fw-bold text-ff-black mb-2 d-block">{{ __('Game PIN') }}</label>
                    <input name="pin" id="pin" value="{{ old('pin', $pin) }}" inputmode="numeric" maxlength="6" required autocomplete="off"
                        class="form-control form-control-lg text-center fw-bold mb-3 @error('pin') is-invalid @enderror" style="font-size: 2rem; letter-spacing: .35em" placeholder="000000">
                    @error('pin')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                    <button class="btn btn-ff-primary btn-lg w-100 fw-bold">{{ __('Enter') }}</button>
                </form>
            </div>
        </div>
    </div>

    @if ($live->isNotEmpty())
        <h2 class="h5 mb-3"><span class="ff-badge-live me-1">{{ __('Live') }}</span> {{ __('Happening now') }}</h2>
        <div class="row g-3 mb-4">
            @foreach ($live as $session)
                <div class="col-md-6 col-xl-4">
                    <div class="ff-card h-100 d-flex flex-column gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <x-ff.church-avatar :church="$session->church" size="sm" />
                            <span class="small text-ff-muted text-truncate">{{ $session->church->name }}</span>
                            <span class="ms-auto ff-badge text-nowrap">{{ __(':n playing', ['n' => $session->players_count]) }}</span>
                        </div>
                        <div class="fw-bold fs-5 text-ff-black">{{ $session->quiz->title }}</div>
                        <div class="small text-ff-muted">{{ $session->status === 'lobby' ? __('Waiting to start — join now!') : __('In progress') }}</div>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="{{ route('games.play', $session) }}" class="btn btn-ff-primary flex-grow-1"><i class="bi bi-play-fill"></i> {{ __('Join game') }}</a>
                            <a href="{{ route('games.scores', $session) }}" class="btn btn-ff-soft" title="{{ __('Watch scores') }}"><i class="bi bi-bar-chart-line"></i></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <x-ff.card :title="__('Recent games')">
                @forelse ($recent as $s)
                    <a href="{{ route('games.scores', $s) }}" class="d-flex align-items-center gap-3 py-2 text-decoration-none @if (! $loop->first) border-top @endif">
                        <x-ff.church-avatar :church="$s->church" size="sm" />
                        <span class="flex-grow-1 min-w-0"><span class="d-block fw-semibold text-ff-black text-truncate">{{ $s->quiz->title }}</span><span class="small text-ff-muted">{{ $s->church->name }} · {{ $s->finished_at?->diffForHumans() }} · {{ trans_choice(':count player|:count players', $s->players_count) }}</span></span>
                        <span class="small fw-semibold text-ff-primary text-nowrap">{{ __('Results') }} <i class="bi bi-chevron-right"></i></span>
                    </a>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No games played yet. Ask your church to host one after service!') }}</p>
                @endforelse
            </x-ff.card>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('My scores')">
                @forelse ($myGames as $p)
                    <div class="d-flex justify-content-between small py-1"><span class="text-truncate">{{ $p->session?->quiz?->title }}</span><strong class="text-ff-black">{{ number_format($p->score) }}</strong></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('Your scores will appear here.') }}</p>
                @endforelse
            </x-ff.card>
        </div>
    </div>
</x-layouts.member>
