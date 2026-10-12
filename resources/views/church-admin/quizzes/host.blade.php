<x-layouts.church-admin :church="$church" :title="$session->quiz->title">
    @php($joinUrl = route('games.index', ['pin' => $session->pin]))
    <div x-data="quizHost({
            initial: @js($state), stateUrl: @js(route('church-admin.quizzes.session.state', [$church, $session])), joinUrl: @js($joinUrl),
            actions: {
                start: @js(route('church-admin.quizzes.session.action', [$church, $session, 'start'])),
                reveal: @js(route('church-admin.quizzes.session.action', [$church, $session, 'reveal'])),
                next: @js(route('church-admin.quizzes.session.action', [$church, $session, 'next'])),
                end: @js(route('church-admin.quizzes.session.action', [$church, $session, 'end'])),
                cancel: @js(route('church-admin.quizzes.session.action', [$church, $session, 'cancel'])),
            } })" class="ff-quiz-arena" x-ref="arena">

        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <a href="{{ route('church-admin.quizzes.index', $church) }}" class="btn btn-sm btn-ff-white" aria-label="{{ __('Back') }}"><i class="bi bi-arrow-left"></i></a>
            <div class="fw-bold fs-5 me-auto">{{ $session->quiz->title }}</div>
            <span class="ff-quiz-chip"><i class="bi bi-people-fill"></i> <span x-text="s.players_count"></span></span>
            <span class="ff-quiz-chip" x-show="s.status !== 'lobby' && s.status !== 'finished'">{{ __('PIN') }} <strong x-text="s.pin"></strong></span>
            <button type="button" class="btn btn-sm btn-ff-white" @click="$refs.arena.requestFullscreen?.()" aria-label="{{ __('Full screen') }}"><i class="bi bi-fullscreen"></i></button>
        </div>

        {{-- Lobby --}}
        <template x-if="s.status === 'lobby'">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7 text-center text-lg-start">
                    <div class="small text-uppercase fw-semibold" style="opacity:.8; letter-spacing:.15em">{{ __('Join on your phone') }}</div>
                    <div class="fs-5 mb-2" style="opacity:.9">{{ __('FaithFellows → Quizzes & games → enter the PIN') }}</div>
                    <div class="ff-quiz-pin" x-text="s.pin"></div>
                    <div class="d-flex flex-wrap gap-2 mt-4 justify-content-center justify-content-lg-start">
                        <button type="button" class="btn btn-lg btn-ff-white fw-bold px-5" @click="act('start')" :disabled="busy || !s.players_count"><i class="bi bi-play-fill"></i> {{ __('Start game') }}</button>
                        <button type="button" class="btn btn-lg btn-outline-light" @click="copyLink()"><i class="bi bi-link-45deg"></i> {{ __('Copy link') }}</button>
                    </div>
                    <p class="small mt-2 mb-0" style="opacity:.75" x-show="!s.players_count">{{ __('Waiting for players to join…') }}</p>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="bg-white rounded-4 p-3 d-inline-block" style="max-width: 240px">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(210)->margin(0)->generate($joinUrl) !!}</div>
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <template x-for="(name, idx) in s.players" :key="idx"><span class="ff-quiz-chip" x-text="name"></span></template>
                    </div>
                </div>
            </div>
        </template>

        {{-- Question / reveal --}}
        <template x-if="s.status === 'question' || s.status === 'reveal'">
            <div class="d-grid gap-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="ff-quiz-chip" x-text="`{{ __('Question') }} ${s.index + 1} / ${s.total}`"></span>
                    <span class="ms-auto ff-quiz-chip"><i class="bi bi-check2-square"></i> <span x-text="s.question?.answered_count ?? 0"></span> / <span x-text="s.players_count"></span> {{ __('answered') }}</span>
                    <div class="ff-quiz-timer" :style="`--p: ${progress}`" x-show="s.status === 'question'"><span x-text="remaining"></span></div>
                </div>
                <div class="ff-quiz-question" x-text="s.question?.text"></div>
                <div class="ff-quiz-options">
                    <template x-for="(opt, i) in s.question?.options || []" :key="i">
                        <div class="ff-quiz-option" :class="[`o${i}`, s.status === 'reveal' && s.reveal?.correct !== i ? 'is-dim' : '', s.status === 'reveal' && s.reveal?.correct === i ? 'is-correct' : '']">
                            <i class="bi" :class="shape(i)"></i><span x-text="opt"></span>
                            <span class="ff-quiz-count" x-show="s.status === 'reveal'" x-text="s.reveal?.counts?.[i] ?? 0"></span>
                        </div>
                    </template>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-lg btn-outline-light" x-show="s.status === 'question'" @click="act('reveal')" :disabled="busy">{{ __('Show answer') }}</button>
                    <button type="button" class="btn btn-lg btn-ff-white fw-bold px-4" x-show="s.status === 'reveal'" @click="act('next')" :disabled="busy">
                        <span x-text="s.index + 1 >= s.total ? '{{ __('Final results') }}' : '{{ __('Next question') }}'"></span> <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
                <div x-show="s.status === 'reveal'" class="ff-quiz-board" style="max-width: 640px; margin-inline: auto; width: 100%">
                    <h3 class="h5 text-center mb-1">{{ __('Leaderboard') }}</h3>
                    <template x-for="row in s.leaderboard.slice(0, 5)" :key="row.rank">
                        <div class="ff-quiz-row"><span class="ff-quiz-rank" x-text="row.rank"></span><span x-text="row.name"></span><span class="ff-quiz-score" x-text="row.score.toLocaleString()"></span></div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Finished: podium --}}
        <template x-if="s.status === 'finished'">
            <div class="text-center">
                <h2 class="display-6 fw-bold">🏆 {{ __('Winners') }}</h2>
                <div class="ff-quiz-podium">
                    <template x-for="place in [2, 1, 3]" :key="place">
                        <div class="p" :class="`p${place}`" x-show="s.leaderboard[place - 1]">
                            <div class="name" x-text="s.leaderboard[place - 1]?.name"></div>
                            <div class="small mb-1" x-text="(s.leaderboard[place - 1]?.score ?? 0).toLocaleString() + ' pts'"></div>
                            <div class="block" x-text="place"></div>
                        </div>
                    </template>
                </div>
                <div class="ff-quiz-board text-start" style="max-width: 640px; margin-inline: auto">
                    <template x-for="row in s.leaderboard.slice(3)" :key="row.rank">
                        <div class="ff-quiz-row"><span class="ff-quiz-rank" x-text="row.rank"></span><span x-text="row.name"></span><span class="ff-quiz-score" x-text="row.score.toLocaleString()"></span></div>
                    </template>
                </div>
                <a href="{{ route('church-admin.quizzes.index', $church) }}" class="btn btn-ff-white mt-4">{{ __('Back to quizzes') }}</a>
            </div>
        </template>

        <template x-if="s.status === 'cancelled'">
            <div class="text-center py-5"><h2>{{ __('Game cancelled') }}</h2></div>
        </template>

        <div class="text-end mt-4" x-show="s.status === 'lobby' || s.status === 'question' || s.status === 'reveal'">
            <button type="button" class="btn btn-sm btn-link text-white-50" @click="if (confirm(@js(__('End the game now and show the results?')))) act('end')">{{ __('End game') }}</button>
        </div>
    </div>
</x-layouts.church-admin>
