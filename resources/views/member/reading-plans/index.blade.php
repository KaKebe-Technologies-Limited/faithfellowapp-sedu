<x-layouts.member :title="__('Reading plans')">
    <x-ff.page-header :title="__('Bible reading plans')" :subtitle="__('Read the Word daily with your church. Build a streak!')" />

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
