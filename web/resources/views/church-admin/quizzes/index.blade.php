<x-layouts.church-admin :church="$church" :title="__('Quizzes & games')">
    <x-ff.page-header :title="__('Quizzes & games')" :subtitle="__('Write multiple-choice quizzes and play them live — members answer on their phones and see the scores climb in real time.')">
        <a href="{{ route('church-admin.quizzes.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New quiz') }}</a>
    </x-ff.page-header>

    <div class="alert alert-info small"><i class="bi bi-phone"></i> {{ __('Every quiz you write is also in the app under Games → From your church, first in the list. Members who follow your church can play it any time at their own pace and are ranked by day, week and all time. Use Host live when you want everyone to play together.') }}</div>

    @foreach ($live as $session)
        <div class="ff-card bg-ff-gradient border-0 text-white d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="ff-badge-live">{{ __('Live') }}</span>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">{{ $session->quiz->title }}</div>
                <div class="small" style="opacity:.9">{{ __('PIN :pin · :n players · :status', ['pin' => $session->pin, 'n' => $session->players_count, 'status' => __(ucfirst($session->status))]) }}</div>
            </div>
            <a href="{{ route('church-admin.quizzes.session', [$church, $session]) }}" class="btn btn-ff-white fw-semibold"><i class="bi bi-display"></i> {{ __('Open host screen') }}</a>
        </div>
    @endforeach

    @if ($quizzes->isEmpty())
        <x-ff.empty-state icon="bi-controller" :title="__('No quizzes yet')" :message="__('Bible trivia after service, youth night games, a Sunday school challenge — create your first quiz in minutes.')">
            <a href="{{ route('church-admin.quizzes.create', $church) }}" class="btn btn-ff-primary mt-2">{{ __('Create a quiz') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-3 mb-4">
            @foreach ($quizzes as $quiz)
                <div class="col-md-6 col-xl-4">
                    <div class="ff-card h-100 d-flex flex-column gap-3">
                        <div class="ff-quiz-card-cover">
                            <div class="position-relative">
                                <div class="small" style="opacity:.8">{{ trans_choice(':count question|:count questions', $quiz->questions_count) }} · {{ trans_choice('played :count time|played :count times', $quiz->sessions_count) }}</div>
                                <div class="fw-bold fs-5 lh-sm">{{ $quiz->title }}</div>
                            </div>
                        </div>
                        @if ($quiz->description)<p class="small mb-0">{{ Str::limit($quiz->description, 120) }}</p>@endif
                        <form method="POST" action="{{ route('church-admin.quizzes.host', [$church, $quiz]) }}" class="mt-auto">@csrf
                            <div class="form-check form-switch small mb-2">
                                <input class="form-check-input" type="checkbox" name="notify" value="1" id="notify-{{ $quiz->id }}" checked>
                                <label class="form-check-label" for="notify-{{ $quiz->id }}">{{ __('Invite all members (in-app + push)') }}</label>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-ff-primary flex-grow-1" @disabled(! $quiz->questions_count)><i class="bi bi-play-fill"></i> {{ __('Host live') }}</button>
                                <a href="{{ route('church-admin.quizzes.edit', [$church, $quiz]) }}" class="btn btn-ff-soft" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('church-admin.quizzes.destroy', [$church, $quiz]) }}" data-ff-confirm="{{ __('Delete this quiz?') }}" class="text-end">@csrf @method('DELETE')
                            <button class="btn btn-link btn-sm p-0 text-danger small">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <x-ff.card :title="__('Recent games')" flush>
            <div class="table-responsive">
                <table class="table ff-table mb-0">
                    <thead><tr><th>{{ __('Quiz') }}</th><th>{{ __('Played') }}</th><th class="text-end">{{ __('Players') }}</th><th>{{ __('Winner') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($recent as $s)
                            <tr>
                                <td class="fw-semibold">{{ $s->quiz->title }}</td>
                                <td class="small">{{ $s->finished_at?->format('D j M, g:i A') }}</td>
                                <td class="text-end">{{ $s->players_count }}</td>
                                <td class="small">@if ($w = $s->players->first())🏆 {{ $w->nickname }} <span class="text-ff-muted">· {{ number_format($w->score) }}</span>@else — @endif</td>
                                <td class="text-end"><a href="{{ route('church-admin.quizzes.session', [$church, $s]) }}" class="btn btn-sm btn-ff-soft">{{ __('Results') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ff.card>
    @endif
</x-layouts.church-admin>
