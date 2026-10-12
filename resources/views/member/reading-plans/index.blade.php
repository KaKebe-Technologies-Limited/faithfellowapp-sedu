<x-layouts.member :title="__('Reading plans')">
    <x-ff.page-header :title="__('Bible reading plans')" :subtitle="__('Read the Word daily with your church. Build a streak!')">
        <a href="{{ route('bible.index') }}" class="btn btn-ff-soft"><i class="bi bi-book"></i> {{ __('Open the Bible') }}</a>
    </x-ff.page-header>

    <div class="row g-4 mb-5">
        <div class="col-lg-7">
            <div class="ff-card h-100">
                <h2 class="h5 mb-1"><i class="bi bi-people text-ff-primary"></i> {{ __('Reading partners') }}</h2>
                <p class="small text-ff-muted">{{ __('Pair up with up to three friends from church. Agree on a time, get reminded together, tell each other when you finish and share a short summary only your circle sees.') }}</p>
                @forelse ($circles as $circle)
                    <a href="{{ route('reading-circles.show', $circle) }}" class="d-flex align-items-center gap-3 p-2 rounded-3 bg-ff-mist mb-2 text-decoration-none">
                        <span class="ff-avatar-stack">@foreach ($circle->members as $m)<x-ff.avatar :user="$m" size="sm" />@endforeach</span>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-block fw-semibold text-ff-black text-truncate">{{ $circle->name }}</span>
                            <span class="small text-ff-muted">{{ $circle->plan?->title }} · {{ $circle->scheduleLabel() }}</span>
                        </span>
                        <i class="bi bi-chevron-right text-ff-muted"></i>
                    </a>
                @empty
                    <p class="small mb-0">{{ __('Start a circle from any plan below, or join a friend’s with their code.') }}</p>
                @endforelse
            </div>
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('reading-circles.join') }}" class="ff-card h-100 bg-ff-gradient border-0 text-white">@csrf
                <h2 class="h5 text-white mb-1"><i class="bi bi-key"></i> {{ __('Got a pairing code?') }}</h2>
                <p class="small" style="opacity:.9">{{ __('Enter the 6-character code your reading partner shared.') }}</p>
                <div class="d-flex gap-2">
                    <input name="code" class="form-control text-uppercase fw-bold" style="letter-spacing:.3em" maxlength="6" minlength="6" required placeholder="ABC123" aria-label="{{ __('Pairing code') }}" autocomplete="off">
                    <button class="btn btn-ff-white fw-semibold">{{ __('Join') }}</button>
                </div>
                @error('code')<div class="small mt-2 bg-white text-danger rounded-3 px-2 py-1">{{ $message }}</div>@enderror
            </form>
        </div>
    </div>

    @if ($mine->isNotEmpty())
        <h2 class="h5 mb-3">{{ __('My plans') }}</h2>
        <div class="row g-4 mb-5">
            @foreach ($mine as $progress)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('reading-plans.show', $progress->plan) }}" class="ff-card ff-card-link ff-card-hover h-100 d-block">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold text-ff-black">{{ $progress->plan->title }}</div>
                                <div class="small text-ff-muted">{{ $progress->church->name }}</div>
                            </div>
                            @if ($progress->current_streak > 0)<span class="ff-badge ff-badge-warning">🔥 {{ $progress->current_streak }}</span>@endif
                        </div>
                        <div class="fs-4 fw-bold text-ff-black my-2">{{ $progress->completed_at ? __('Completed! 🎉') : __('Day :day of :total', ['day' => $progress->nextDay(), 'total' => $progress->plan->days_count]) }}</div>
                        <x-ff.progress :value="$progress->percent()" size="sm" />
                        <div class="small text-ff-muted mt-2">{{ $progress->percent() }}% · {{ __('Longest streak: :n days', ['n' => $progress->longest_streak]) }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="h5 mb-3">{{ __('Plans from your churches') }}</h2>
    @if ($available->isEmpty())
        <x-ff.empty-state icon="bi-journal-check" :title="__('No plans available')" :message="__('Your churches have not published any reading plans yet.')" />
    @else
        <div class="row g-4">
            @foreach ($available as $plan)
                <div class="col-md-6 col-xl-4">
                    <div class="ff-card h-100 d-flex flex-column">
                        <div class="ff-feature-icon"><i class="bi bi-journal-bookmark"></i></div>
                        <h3 class="h6 mb-1">{{ $plan->title }}</h3>
                        <div class="small text-ff-muted mb-2">{{ $plan->church->name }} · {{ trans_choice(':count day|:count days', $plan->days_count) }}</div>
                        <p class="small mb-3">{{ $plan->description }}</p>
                        <form method="POST" action="{{ route('reading-plans.start', $plan) }}" class="mt-auto">@csrf
                            <button class="btn btn-ff-primary w-100">{{ __('Start plan') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.member>
