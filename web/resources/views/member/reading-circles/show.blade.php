<x-layouts.member :title="$circle->name">
    <x-ff.page-header :title="$circle->name" :subtitle="$circle->plan->title.' · '.$circle->scheduleLabel()"
        :breadcrumbs="[__('Reading plans') => route('reading-plans.index'), $circle->plan->title => route('reading-plans.show', $circle->plan), $circle->name => null]" />

    @php
        $members = $circle->members;
        $doneCount = $entries->whereNotNull('completed_at')->count();
    @endphp

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <a href="{{ route('reading-circles.show', [$circle, 'day' => max(1, $day - 1)]) }}" class="btn btn-ff-ghost btn-icon" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-left"></i></a>
                    <div class="text-center">
                        <div class="ff-eyebrow justify-content-center">{{ __('Day :day of :total', ['day' => $day, 'total' => $circle->plan->days_count]) }}</div>
                        @if ($day === $currentDay)<span class="ff-badge ff-badge-primary mt-1">{{ __('Reading together today') }}</span>@endif
                    </div>
                    <a href="{{ route('reading-circles.show', [$circle, 'day' => min($circle->plan->days_count, $day + 1)]) }}" class="btn btn-ff-ghost btn-icon" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
                </div>
                @if ($entry)
                    <p class="text-center fs-3 fw-bold text-ff-primary mt-2 mb-1">{{ $entry['passages'] }}</p>
                    @if (! empty($entry['reflection']))<p class="text-center ff-lead">{{ $entry['reflection'] }}</p>@endif
                    <div class="text-center mb-2"><span class="small text-ff-muted">{{ trans_choice(':done of :count partner has finished|:done of :count partners have finished', $members->count(), ['done' => $doneCount, 'count' => $members->count()]) }}</span></div>
                    <x-ff.progress :value="$members->count() ? $doneCount / $members->count() * 100 : 0" />

                    <details class="mt-4" @if (! $mine?->completed_at) open @endif>
                        <summary class="fw-semibold text-ff-primary" style="cursor:pointer"><i class="bi bi-book"></i> {{ __('Read :ref', ['ref' => $entry['passages']]) }}</summary>
                        <div class="pt-3" x-data="biblePassage(@js(route('bible.passage', ['ref' => $entry['passages'], 'partial' => 1])))">
                            <div x-show="state === 'loading'" class="ff-passage-skeleton"><span style="width:40%"></span><span></span><span></span><span style="width:70%"></span></div>
                            <div x-show="state === 'ready'" x-html="html" x-cloak></div>
                            <p x-show="state === 'error'" x-cloak class="small text-danger mb-0">{{ __('We could not load the passage. Check your connection.') }}</p>
                        </div>
                    </details>
                @endif
            </div>

            {{-- My summary --}}
            <form method="POST" action="{{ route('reading-circles.complete', [$circle, $day]) }}" class="ff-card">@csrf
                <h2 class="h6 mb-1">{{ $mine?->completed_at ? __('Your summary') : __('Finished reading? Share what stood out') }}</h2>
                <p class="small text-ff-muted mb-2"><i class="bi bi-lock"></i> {{ __('Only the people in this circle can see summaries.') }}</p>
                <textarea name="summary" rows="4" class="form-control ff-scripture" style="font-size: 1rem" maxlength="3000"
                    placeholder="{{ __('A verse that spoke to you, a question, a prayer…') }}">{{ old('summary', $mine?->summary) }}</textarea>
                <button class="btn btn-ff-primary mt-3">
                    @if ($mine?->completed_at)<i class="bi bi-save"></i> {{ __('Update summary') }}@else<i class="bi bi-check2-circle"></i> {{ __('I’ve read it — tell my partners') }}@endif
                </button>
            </form>

            {{-- Partners' summaries --}}
            <x-ff.card :title="__('Summaries for Day :day', ['day' => $day])">
                @php($shared = $entries->filter(fn ($e) => $e->user_id !== auth()->id() && $e->completed_at))
                @forelse ($shared as $e)
                    @php($partner = $members->firstWhere('id', $e->user_id))
                    <div class="ff-summary-card mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <x-ff.avatar :user="$partner" size="sm" />
                            <strong class="text-ff-black small">{{ $partner?->name }}</strong>
                            <span class="small-xs text-ff-muted ms-auto">{{ $e->completed_at->diffForHumans() }}</span>
                        </div>
                        <div class="ff-summary-text">{{ $e->summary ?: __('Read it ✔ — no summary yet.') }}</div>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No partner has finished this day yet. Your summary will encourage them!') }}</p>
                @endforelse
            </x-ff.card>
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card text-center">
                <div class="small text-ff-muted mb-2">{{ __('Pairing code') }}</div>
                <div class="ff-circle-code" data-ff-copy="{{ $circle->code }}" role="button" title="{{ __('Copy code') }}">{{ $circle->code }}</div>
                <p class="small text-ff-muted mt-2 mb-2">{{ __(':n of :max members. Share the code with church friends — they join from Reading plans → Join with code.', ['n' => $members->count(), 'max' => $circle->max_members]) }}</p>
                <a class="btn btn-sm btn-ff-soft" target="_blank" rel="noopener"
                   href="https://wa.me/?text={{ urlencode(__('Join my reading circle “:name” on FaithFellows (:plan). Code: :code — :url', ['name' => $circle->name, 'plan' => $circle->plan->title, 'code' => $circle->code, 'url' => route('reading-plans.index')])) }}"><i class="bi bi-whatsapp"></i> {{ __('Share on WhatsApp') }}</a>
            </div>

            <x-ff.card :title="__('Partners')">
                @foreach ($members as $m)
                    @php($e = $entries[$m->id] ?? null)
                    <div class="ff-circle-member">
                        <x-ff.avatar :user="$m" size="sm" />
                        <span class="min-w-0"><span class="d-block small fw-semibold text-ff-black text-truncate">{{ $m->name }}@if ($m->id === auth()->id()) <span class="text-ff-muted fw-normal">({{ __('you') }})</span>@endif</span>
                            @if ($m->pivot->role === 'owner')<span class="small-xs text-ff-muted">{{ __('Started the circle') }}</span>@endif</span>
                        <span class="ff-circle-status {{ $e?->completed_at ? 'text-success' : 'text-ff-muted' }}">
                            @if ($e?->completed_at)<i class="bi bi-check-circle-fill"></i> {{ __('Read') }}@else<i class="bi bi-hourglass"></i> {{ __('Not yet') }}@endif
                        </span>
                    </div>
                @endforeach
            </x-ff.card>

            @if ($history->isNotEmpty())
                <x-ff.card :title="__('Our journey')">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach ($history as $d => $done)
                            <a href="{{ route('reading-circles.show', [$circle, 'day' => $d]) }}" class="ff-badge {{ $done >= $members->count() ? 'ff-badge-success' : '' }}" title="{{ __(':done read', ['done' => $done]) }}">{{ __('Day :d', ['d' => $d]) }}</a>
                        @endforeach
                    </div>
                </x-ff.card>
            @endif

            @if ($isOwner)
                <details class="ff-card">
                    <summary class="fw-semibold" style="cursor:pointer"><i class="bi bi-alarm"></i> {{ __('Change reading time') }}</summary>
                    <form method="POST" action="{{ route('reading-circles.update', $circle) }}" class="mt-3">@csrf @method('PUT')
                        <x-ff.input name="name" :label="__('Circle name')" :value="$circle->name" maxlength="80" required />
                        <x-ff.input type="time" name="reading_time" :label="__('We read at')" :value="substr($circle->reading_time, 0, 5)" required />
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            @foreach (\App\Models\ReadingCircle::DAYS as $i => $d)
                                <input type="checkbox" class="btn-check" name="reading_days[]" value="{{ $i }}" id="ed-{{ $i }}" @checked(in_array($i, $circle->reading_days ?? [], true)) autocomplete="off">
                                <label class="btn btn-sm btn-outline-secondary rounded-pill px-2" for="ed-{{ $i }}">{{ __($d) }}</label>
                            @endforeach
                        </div>
                        <button class="btn btn-ff-primary btn-sm w-100">{{ __('Save for everyone') }}</button>
                    </form>
                </details>
            @endif

            <form method="POST" action="{{ route('reading-circles.leave', $circle) }}" data-ff-confirm="{{ __('Leave this reading circle?') }}">@csrf @method('DELETE')
                <button class="btn btn-ff-ghost text-danger w-100 btn-sm">{{ __('Leave circle') }}</button>
            </form>
        </aside>
    </div>

    @if ($entry)
        @include('partials.note-taker', ['context' => 'plan:'.$circle->plan->id.':day:'.$day, 'reference' => __('Day :day', ['day' => $day]).' · '.$entry['passages'], 'churchId' => $circle->church_id])
    @endif
</x-layouts.member>
