<x-layouts.member :title="$session->quiz->title">
    <div x-data="quizPlayer({ initial: @js($state), stateUrl: @js(route('games.state', $session)), answerUrl: @js(route('games.answer', $session)) })" class="ff-quiz-arena">
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <a href="{{ route('games.index') }}" class="btn btn-sm btn-ff-white" aria-label="{{ __('Leave') }}"><i class="bi bi-arrow-left"></i></a>
            <div class="me-auto min-w-0">
                <div class="small" style="opacity:.8">{{ $session->church->name }}</div>
                <div class="fw-bold text-truncate">{{ $session->quiz->title }}</div>
            </div>
            <span class="ff-quiz-chip d-none d-sm-inline-flex" x-show="s.me"><i class="bi bi-person-fill"></i> <span x-text="s.me?.name"></span></span>
            <span class="ff-quiz-chip" x-show="s.me?.rank && s.status !== 'lobby'"><i class="bi bi-trophy-fill"></i> #<span x-text="s.me?.rank"></span></span>
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
                    <div class="ff-quiz-bar flex-grow-1" :class="remaining <= 3 && 'is-urgent'"><div :style="`width:${progress}%`"></div></div>
                    <strong class="fs-3 ff-quiz-seconds" :class="remaining <= 3 && 'is-urgent'" x-text="remaining"></strong>
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
                    <div class="ff-quiz-locked">
                        <span class="ff-quiz-option" :class="`o${s.me.answer}`"><i class="bi" :class="shape(s.me.answer)"></i><span x-text="s.question?.options?.[s.me.answer]"></span></span>
                        <div><strong>{{ __('Answer locked in!') }}</strong> <span style="opacity:.85">{{ __('The answer shows when the time is up.') }}</span></div>
                    </div>
                </template>
                @include('partials.quiz-live-board', ['rows' => 'board(5)'])
            </div>
        </template>

        <template x-if="s.status === 'reveal'">
            <div class="d-grid gap-3" style="max-width: 640px; margin-inline: auto; width: 100%">
                <div class="ff-quiz-feedback" :class="s.me?.correct ? 'is-right' : 'is-wrong'">
                    <div class="big" x-text="s.me?.correct ? '🎉' : (s.me?.answer === null || s.me?.answer === undefined ? '⌛' : '🙈')"></div>
                    <h2 class="fw-bold mb-1" x-text="s.me?.correct ? '{{ __('Correct!') }}' : (s.me?.answer === null || s.me?.answer === undefined ? '{{ __('Time’s up') }}' : '{{ __('Not this time') }}')"></h2>
                    <div class="fs-5" x-show="s.me?.correct" x-text="`+${(s.me?.points ?? 0).toLocaleString()} {{ __('points') }}`"></div>
                    <div class="small mt-2" style="opacity:.9" x-text="`{{ __('Answer') }}: ${s.question?.options?.[s.reveal?.correct] ?? ''}`"></div>
                    <div class="small mt-1 fw-semibold" x-show="(s.me?.streak ?? 0) >= 2" x-text="`🔥 ${s.me?.streak} {{ __('in a row') }}`"></div>
                </div>
                <div class="ff-quiz-next">
                    <span x-text="s.is_last ? '{{ __('Final results in') }}' : '{{ __('Next question in') }}'"></span>
                    <strong x-text="nextIn"></strong>
                    <div class="ff-quiz-bar flex-grow-1"><div :style="`width:${nextProgress}%`"></div></div>
                </div>
                @include('partials.quiz-live-board', ['rows' => 'board(5)'])
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
