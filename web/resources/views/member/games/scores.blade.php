<x-layouts.member :title="__('Live scores')">
    <div x-data="quizBoard({ initial: @js($state), stateUrl: @js(route('games.state', $session)) })" class="ff-quiz-arena">
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <a href="{{ route('games.index') }}" class="btn btn-sm btn-ff-white" aria-label="{{ __('Back') }}"><i class="bi bi-arrow-left"></i></a>
            <div class="me-auto min-w-0">
                <div class="small" style="opacity:.8">{{ $session->church->name }} · {{ __('Live scores') }}</div>
                <div class="fw-bold fs-5 text-truncate">{{ $session->quiz->title }}</div>
            </div>
            <span class="ff-badge-live" x-show="['lobby','question','reveal'].includes(s.status)">{{ __('Live') }}</span>
            <span class="ff-quiz-chip"><i class="bi bi-people-fill"></i> <span x-text="s.players_count"></span></span>
            @if ($session->isActive())
                <a href="{{ route('games.play', $session) }}" class="btn btn-sm btn-ff-white fw-semibold">{{ __('Join the game') }}</a>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-lg-5" x-show="s.question">
                <div class="small text-uppercase fw-semibold mb-2" style="opacity:.8" x-text="s.status === 'finished' ? '{{ __('Last question') }}' : `{{ __('Question') }} ${(s.index ?? 0) + 1} / ${s.total}`"></div>
                <div class="ff-quiz-question fs-5 mb-3" x-text="s.question?.text"></div>
                <div class="ff-quiz-bar mb-3" x-show="s.status === 'question'"><div :style="`width:${progress}%`"></div></div>
                <div class="d-grid gap-2" x-show="s.reveal">
                    <template x-for="(opt, i) in s.question?.options || []" :key="i">
                        <div class="ff-quiz-option" style="min-height: 56px; font-size: 1rem" :class="[`o${i}`, s.reveal?.correct === i ? 'is-correct' : 'is-dim']">
                            <i class="bi" :class="shape(i)"></i><span x-text="opt"></span><span class="ff-quiz-count" x-text="s.reveal?.counts?.[i] ?? 0"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div :class="s.question ? 'col-lg-7' : 'col-12'">
                <template x-if="s.status !== 'finished'">
                    <div class="d-grid gap-3">
                        <div class="ff-quiz-next" x-show="s.status === 'reveal'">
                            <span x-text="s.is_last ? '{{ __('Final results in') }}' : '{{ __('Next question in') }}'"></span>
                            <strong x-text="nextIn"></strong>
                            <div class="ff-quiz-bar flex-grow-1"><div :style="`width:${nextProgress}%`"></div></div>
                        </div>
                        @include('partials.quiz-live-board', ['rows' => 's.leaderboard'])
                    </div>
                </template>
                <template x-if="s.status === 'finished'">
                    <div>
                        <h2 class="h4 mb-3">🏆 {{ __('Final leaderboard') }}</h2>
                        <div class="ff-quiz-board">
                            <template x-for="row in s.leaderboard" :key="row.id">
                                <div class="ff-quiz-row" :class="{ 'is-me': row.me, 'is-top': row.rank <= 3 }">
                                    <span class="ff-quiz-rank" x-text="medal(row.rank)"></span>
                                    <span class="ff-quiz-name" x-text="row.name"></span>
                                    <span class="small" style="opacity:.7" x-text="`${row.correct} ✓`"></span>
                                    <span class="ff-quiz-score" x-text="row.score.toLocaleString()"></span>
                                </div>
                            </template>
                            <p x-show="!s.leaderboard.length" style="opacity:.8">{{ __('No players yet.') }}</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-layouts.member>
