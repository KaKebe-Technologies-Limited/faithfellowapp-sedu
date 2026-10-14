{{-- Live leaderboard for quiz screens (inside a quizBase Alpine component). Expects $rows: an Alpine expression for the rows. --}}
<div class="ff-quiz-live">
    <div class="ff-quiz-live-head">
        <span class="ff-quiz-live-dot" x-show="['question', 'reveal'].includes(s.status)"></span>
        <span class="fw-bold">{{ $title ?? __('Live scores') }}</span>
        <span class="ms-auto small" style="opacity:.8" x-show="s.status === 'question'" x-text="`${s.question?.answered_count ?? 0} / ${s.players_count} {{ __('answered') }}`"></span>
    </div>
    <div class="ff-quiz-board">
        <template x-for="row in {{ $rows }}" :key="row.id">
            <div class="ff-quiz-row" :class="{ 'is-me': row.me, 'is-gap': row.gap, 'is-top': row.rank <= 3 }" :data-flip="row.id">
                <span class="ff-quiz-rank" x-text="medal(row.rank)"></span>
                <span class="ff-quiz-name" x-text="row.name"></span>
                <span class="ff-quiz-move is-up" x-show="move(row) > 0" x-text="`▲ ${move(row)}`"></span>
                <span class="ff-quiz-move is-down" x-show="move(row) < 0" x-text="`▼ ${-move(row)}`"></span>
                <span class="ff-quiz-streak" x-show="row.streak >= 2" :title="`${row.streak} {{ __('in a row') }}`" x-text="`🔥 ${row.streak}`"></span>
                <span class="ff-quiz-gain" x-show="row.gained > 0" x-text="`+${row.gained.toLocaleString()}`"></span>
                <span class="ff-quiz-score" x-text="row.score.toLocaleString()"></span>
            </div>
        </template>
        <p class="small mb-0" style="opacity:.8" x-show="!(s.leaderboard || []).length">{{ __('Scores appear as soon as someone answers.') }}</p>
    </div>
</div>
