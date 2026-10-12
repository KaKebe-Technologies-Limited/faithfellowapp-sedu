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
                            @if ($done)<span class="ff-badge ff-badge-success mt-1"><i class="bi bi-check2"></i> {{ __('Read') }}</span>@endif
                        </div>
                        <a href="{{ route('reading-plans.show', [$plan, 'day' => min($plan->days_count, $day + 1)]) }}" class="btn btn-ff-ghost btn-icon" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
                    </div>
                    @if ($entry)
                        @if (! empty($entry['title']))<h2 class="h4 text-center">{{ $entry['title'] }}</h2>@endif
                        <p class="text-center fs-3 fw-bold text-ff-primary mt-3 mb-1">{{ $entry['passages'] }}</p>
                        <p class="text-center mb-4"><a href="{{ route('bible.passage', ['ref' => $entry['passages']]) }}" class="small"><i class="bi bi-book"></i> {{ __('Open in the Bible reader') }}</a></p>
                        @if (! empty($entry['reflection']))<p class="ff-lead text-center">{{ $entry['reflection'] }}</p>@endif

                        {{-- The day's scripture, loaded without slowing the page --}}
                        <div class="border-top pt-4 mt-2" x-data="biblePassage(@js(route('bible.passage', ['ref' => $entry['passages'], 'partial' => 1])))">
                            <div x-show="state === 'loading'" class="ff-passage-skeleton" aria-label="{{ __('Loading scripture…') }}">
                                <span style="width: 40%"></span><span></span><span></span><span style="width: 85%"></span><span></span><span style="width: 60%"></span>
                            </div>
                            <div x-show="state === 'ready'" x-html="html" x-cloak></div>
                            <p x-show="state === 'error'" x-cloak class="small text-danger mb-0">{{ __('We could not load the passage. Check your connection.') }}</p>
                        </div>

                        <div class="text-center d-flex flex-wrap gap-2 justify-content-center mt-4 pt-3 border-top">
                            @if ($done)
                                <span class="btn btn-success rounded-pill disabled"><i class="bi bi-check2"></i> {{ __('Read') }}</span>
                            @else
                                <form method="POST" action="{{ route('reading-plans.complete', $plan) }}">@csrf
                                    <input type="hidden" name="day" value="{{ $day }}">
                                    <button class="btn btn-ff-primary px-4"><i class="bi bi-check2-circle"></i> {{ __('Mark as read') }}</button>
                                </form>
                            @endif
                            @foreach ($circles as $circle)
                                <a href="{{ route('reading-circles.show', [$circle, 'day' => $day]) }}" class="btn btn-ff-soft"><i class="bi bi-people"></i> {{ __('Share with :circle', ['circle' => $circle->name]) }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            @if ($progress)
                <div class="ff-card text-center">
                    <x-ff.ring :value="$progress->percent()" :size="150" :label="$progress->percent().'%'" :sub="__('complete')" />
                    <div class="row mt-3 text-center">
                        <div class="col"><div class="fs-4 fw-bold text-ff-black">🔥 {{ $progress->current_streak }}</div><div class="small text-ff-muted">{{ __('Current streak') }}</div></div>
                        <div class="col"><div class="fs-4 fw-bold text-ff-black">{{ $progress->longest_streak }}</div><div class="small text-ff-muted">{{ __('Longest streak') }}</div></div>
                    </div>
                </div>
            @endif

            @if ($canCircle)
                @include('member.reading-circles._card', ['plan' => $plan, 'circles' => $circles])
            @endif

            @if ($progress)
                <form method="POST" action="{{ route('reading-plans.leave', $plan) }}" data-ff-confirm="{{ __('Leave this plan? Your progress will be lost.') }}">@csrf @method('DELETE')
                    <button class="btn btn-ff-ghost text-danger w-100">{{ __('Leave plan') }}</button>
                </form>
            @endif
        </aside>
    </div>

    @if ($progress && $entry)
        @include('partials.note-taker', ['context' => 'plan:'.$plan->id.':day:'.$day, 'reference' => __('Day :day', ['day' => $day]).' · '.$entry['passages'], 'churchId' => $plan->church_id])
    @endif
</x-layouts.member>
