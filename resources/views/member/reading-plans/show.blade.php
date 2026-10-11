<x-layouts.member :title="$plan->title">
    <x-ff.page-header :title="$plan->title" :subtitle="$plan->church->name.' · '.trans_choice(':count day|:count days', $plan->days_count)"
        :breadcrumbs="[__('Reading plans') => route('reading-plans.index'), $plan->title => null]" />

    <div class="row g-4">
        <div class="col-lg-8">
            @if (! $progress)
                <div class="ff-card text-center p-5">
                    <p class="ff-lead">{{ $plan->description }}</p>
                    <form method="POST" action="{{ route('reading-plans.start', $plan) }}">@csrf
                        <button class="btn btn-ff-primary btn-lg px-5">{{ __('Start this plan') }}</button>
                    </form>
                </div>
            @else
                @php($done = in_array($day, $progress->completed_days ?? [], true))
                <div class="ff-card p-4 p-md-5">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <a href="{{ route('reading-plans.show', [$plan, 'day' => max(1, $day - 1)]) }}" class="btn btn-ff-ghost btn-icon" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-left"></i></a>
                        <div class="text-center">
                            <div class="ff-eyebrow justify-content-center">{{ __('Day :day of :total', ['day' => $day, 'total' => $plan->days_count]) }}</div>
                        </div>
                        <a href="{{ route('reading-plans.show', [$plan, 'day' => min($plan->days_count, $day + 1)]) }}" class="btn btn-ff-ghost btn-icon" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
                    </div>
                    @if ($entry)
                        @if ($entry['title'])<h2 class="h4 text-center">{{ $entry['title'] }}</h2>@endif
                        <p class="text-center fs-3 fw-bold text-ff-primary my-4">{{ $entry['passages'] }}</p>
                        @if ($entry['reflection'])<p class="ff-lead text-center">{{ $entry['reflection'] }}</p>@endif
                        <div class="text-center d-flex flex-wrap gap-2 justify-content-center">
                            <a href="https://www.bible.com/search/bible?q={{ urlencode($entry['passages']) }}" target="_blank" rel="noopener" class="btn btn-ff-outline"><i class="bi bi-book"></i> {{ __('Open in Bible app') }}</a>
                            @if ($done)
                                <span class="btn btn-success rounded-pill disabled"><i class="bi bi-check2"></i> {{ __('Read') }}</span>
                            @else
                                <form method="POST" action="{{ route('reading-plans.complete', $plan) }}">@csrf
                                    <input type="hidden" name="day" value="{{ $day }}">
                                    <button class="btn btn-ff-primary px-4"><i class="bi bi-check2-circle"></i> {{ __('Mark as read') }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
        @if ($progress)
            <aside class="col-lg-4 d-grid gap-4 align-content-start">
                <div class="ff-card text-center">
                    <x-ff.ring :value="$progress->percent()" :size="150" :label="$progress->percent().'%'" :sub="__('complete')" />
                    <div class="row mt-3 text-center">
                        <div class="col"><div class="fs-4 fw-bold text-ff-black">🔥 {{ $progress->current_streak }}</div><div class="small text-ff-muted">{{ __('Current streak') }}</div></div>
                        <div class="col"><div class="fs-4 fw-bold text-ff-black">{{ $progress->longest_streak }}</div><div class="small text-ff-muted">{{ __('Longest streak') }}</div></div>
                    </div>
                </div>
                <form method="POST" action="{{ route('reading-plans.leave', $plan) }}" data-ff-confirm="{{ __('Leave this plan? Your progress will be lost.') }}">@csrf @method('DELETE')
                    <button class="btn btn-ff-ghost text-danger w-100">{{ __('Leave plan') }}</button>
                </form>
            </aside>
        @endif
    </div>
</x-layouts.member>
