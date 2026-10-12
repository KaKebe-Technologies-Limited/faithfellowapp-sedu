<x-layouts.member :title="$session->quiz->title">
    <div x-data="quizPlayer({ initial: @js($state), stateUrl: @js(route('games.state', $session)), answerUrl: @js(route('games.answer', $session)) })" class="ff-quiz-arena">
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <a href="{{ route('games.index') }}" class="btn btn-sm btn-ff-white" aria-label="{{ __('Leave') }}"><i class="bi bi-arrow-left"></i></a>
            <div class="me-auto min-w-0">
                <div class="small" style="opacity:.8">{{ $session->church->name }}</div>
                <div class="fw-bold text-truncate">{{ $session->quiz->title }}</div>
            </div>
            <span class="ff-quiz-chip" x-show="s.me"><i class="bi bi-person-fill"></i> <span x-text="s.me?.name"></span></span>
            <span class="ff-quiz-chip" x-show="s.me"><i class="bi bi-star-fill"></i> <span x-text="(s.me?.score ?? 0).toLocaleString()"></span></span>
        </div>

        <template x-if="s.status === 'lobby'">
            <div class="text-center py-5">
                <div class="display-1 mb-3">🎮</div>
                <h2 class="fw-bold">{{ __('You’re in!') }}</h2>
                <p class="fs-5" style="opacity:.9">{{ __('See your name on the screen? Get ready…') }}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                    <template x-for="(name, idx) in s.players" :key="idx"><span class="ff-quiz-chip" x-text="name"></span></template>
                </div>
            </div>
        </template>

        <template x-if="s.status === 'question'">
            <div class="d-grid gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="ff-quiz-chip" x-text="`${s.index + 1} / ${s.total}`"></span>
                    <div class="ff-quiz-bar flex-grow-1"><div :style="`width:${progress}%`"></div></div>
                    <strong class="fs-4" x-text="remaining"></strong>
                </div>
                <div class="ff-quiz-question" x-text="s.question?.text"></div>
                <template x-if="s.me?.answer === null || s.me?.answer === undefined">
                    <div class="ff-quiz-options">
                        <template x-for="(opt, i) in s.question?.options || []" :key="i">
                            <button type="button" class="ff-quiz-option" :class="`o${i}`" @click="answer(i)" :disabled="busy || remaining <= 0">
                                <i class="bi" :class="shape(i)"></i><span x-text="opt"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="s.me?.answer !== null && s.me?.answer !== undefined">
                    <div class="text-center py-4">
                        <div class="display-4 mb-2">⏳</div>
                        <h3 class="fw-bold">{{ __('Answer locked in!') }}</h3>
                        <p style="opacity:.9">{{ __('Waiting for everyone else…') }}</p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="s.status === 'reveal'">
            <div class="d-grid gap-3" style="max-width: 640px; margin-inline: auto">
                <div class="ff-quiz-feedback" :class="s.me?.correct ? 'is-right' : 'is-wrong'">
                    <div class="big" x-text="s.me?.correct ? '🎉' : (s.me?.answer === null || s.me?.answer === undefined ? '⌛' : '🙈')"></div>
                    <h2 class="fw-bold mb-1" x-text="s.me?.correct ? '{{ __('Correct!') }}' : (s.me?.answer === null || s.me?.answer === undefined ? '{{ __('Time’s up') }}' : '{{ __('Not this time') }}')"></h2>
                    <div class="fs-5" x-show="s.me?.correct" x-text="`+${(s.me?.points ?? 0).toLocaleString()} {{ __('points') }}`"></div>
                    <div class="small mt-2" style="opacity:.9" x-text="`{{ __('Answer') }}: ${s.question?.options?.[s.reveal?.correct] ?? ''}`"></div>
                </div>
                <div class="text-center fs-5">{{ __('You are') }} <strong x-text="`#${s.me?.rank ?? '–'}`"></strong> · <span x-text="(s.me?.score ?? 0).toLocaleString()"></span> {{ __('pts') }}</div>
                <div class="ff-quiz-board">
                    <template x-for="row in s.leaderboard.slice(0, 5)" :key="row.rank">
                        <div class="ff-quiz-row" :class="row.me && 'is-me'"><span class="ff-quiz-rank" x-text="row.rank"></span><span x-text="row.name"></span><span class="ff-quiz-score" x-text="row.score.toLocaleString()"></span></div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="s.status === 'finished'">
            <div class="text-center">
                <h2 class="display-6 fw-bold">{{ __('Final results') }}</h2>
                <p class="fs-5" x-show="s.me">{{ __('You finished') }} <strong x-text="`#${s.me?.rank}`"></strong> {{ __('with') }} <strong x-text="(s.me?.score ?? 0).toLocaleString()"></strong> {{ __('points') }}</p>
                <div class="ff-quiz-podium">
                    <template x-for="place in [2, 1, 3]" :key="place">
                        <div class="p" :class="`p${place}`" x-show="s.leaderboard[place - 1]">
                            <div class="name" x-text="s.leaderboard[place - 1]?.name"></div>
                            <div class="small mb-1" x-text="(s.leaderboard[place - 1]?.score ?? 0).toLocaleString() + ' pts'"></div>
                            <div class="block" x-text="place"></div>
                        </div>
                    </template>
                </div>
                <a href="{{ route('games.index') }}" class="btn btn-ff-white">{{ __('More games') }}</a>
            </div>
        </template>

        <template x-if="s.status === 'cancelled'">
            <div class="text-center py-5"><h2>{{ __('This game was cancelled.') }}</h2><a href="{{ route('games.index') }}" class="btn btn-ff-white mt-3">{{ __('Back') }}</a></div>
        </template>
    </div>
</x-layouts.member>
