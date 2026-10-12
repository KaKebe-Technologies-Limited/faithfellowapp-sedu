<x-layouts.church-admin :church="$church" :title="$quiz->exists ? __('Edit quiz') : __('New quiz')">
    <x-ff.page-header :title="$quiz->exists ? __('Edit quiz') : __('New quiz')" :breadcrumbs="[__('Quizzes & games') => route('church-admin.quizzes.index', $church), ($quiz->exists ? $quiz->title : __('New')) => null]" />

    <form method="POST" action="{{ $quiz->exists ? route('church-admin.quizzes.update', [$church, $quiz]) : route('church-admin.quizzes.store', $church) }}" novalidate
          x-data="{
              questions: @js($questions),
              colors: ['#E21B3C', '#1368CE', '#D89E00', '#26890C'],
              shapes: ['bi-triangle-fill', 'bi-diamond-fill', 'bi-circle-fill', 'bi-square-fill'],
              add() { this.questions.push({ question: '', options: ['', '', '', ''], correct: 0, time_limit: 20, points: 1000 }); this.$nextTick(() => this.$refs.list.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' })); },
              remove(i) { if (this.questions.length > 1) this.questions.splice(i, 1); },
              move(i, d) { const j = i + d; if (j < 0 || j >= this.questions.length) return; [this.questions[i], this.questions[j]] = [this.questions[j], this.questions[i]]; },
          }">
        @csrf
        @if ($quiz->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="ff-card position-sticky" style="top: 90px">
                    <x-ff.input name="title" :label="__('Quiz title')" :value="$quiz->title" required maxlength="160" :placeholder="__('e.g. Bible Heroes Trivia')" />
                    <x-ff.textarea name="description" :label="__('Description')" :value="$quiz->description" rows="3" maxlength="1000" :placeholder="__('Shown to players in the lobby')" />
                    <div class="small text-ff-muted mb-3"><i class="bi bi-lightning-charge"></i> {{ __('Faster correct answers earn more points (up to the full value). Wrong answers score 0.') }}</div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold"><span x-text="questions.length"></span> {{ __('questions') }}</span>
                        <button type="button" class="btn btn-sm btn-ff-soft" @click="add()" :disabled="questions.length >= 50"><i class="bi bi-plus-lg"></i> {{ __('Add question') }}</button>
                    </div>
                    @if ($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
                    <button class="btn btn-ff-primary btn-lg w-100">{{ $quiz->exists ? __('Save quiz') : __('Create quiz') }}</button>
                </div>
            </div>

            <div class="col-lg-8 d-grid gap-3" x-ref="list">
                <template x-for="(q, i) in questions" :key="i">
                    <div class="ff-quiz-q-editor">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="ff-badge ff-badge-primary" x-text="'{{ __('Question') }} ' + (i + 1)"></span>
                            <div class="ms-auto d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-ff-ghost" @click="move(i, -1)" :disabled="i === 0" aria-label="{{ __('Move up') }}"><i class="bi bi-arrow-up"></i></button>
                                <button type="button" class="btn btn-sm btn-ff-ghost" @click="move(i, 1)" :disabled="i === questions.length - 1" aria-label="{{ __('Move down') }}"><i class="bi bi-arrow-down"></i></button>
                                <button type="button" class="btn btn-sm btn-ff-ghost text-danger" @click="remove(i)" :disabled="questions.length === 1" aria-label="{{ __('Remove question') }}"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                        <textarea class="form-control form-control-lg fw-semibold mb-3" rows="2" maxlength="300" required :name="`questions[${i}][question]`" x-model="q.question" placeholder="{{ __('Type your question…') }}"></textarea>
                        <div class="row g-2">
                            <template x-for="(opt, j) in q.options" :key="j">
                                <div class="col-md-6">
                                    <label class="ff-opt">
                                        <span class="ff-opt-dot" :style="`background:${colors[j]}`"><i class="bi" :class="shapes[j]"></i></span>
                                        <input class="form-control" maxlength="120" :name="`questions[${i}][options][${j}]`" x-model="q.options[j]" :placeholder="j < 2 ? '{{ __('Answer (required)') }}' : '{{ __('Answer (optional)') }}'">
                                        <input type="radio" class="form-check-input m-0" :name="`questions[${i}][correct]`" :value="j" x-model.number="q.correct" :aria-label="'{{ __('Correct answer') }}'" title="{{ __('Mark as correct') }}">
                                    </label>
                                </div>
                            </template>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center mt-2 small">
                            <span class="text-success"><i class="bi bi-check-circle"></i> {{ __('Tick the correct answer') }}</span>
                            <label class="d-flex align-items-center gap-2 ms-auto">{{ __('Time') }}
                                <select class="form-select form-select-sm" style="width:auto" :name="`questions[${i}][time_limit]`" x-model.number="q.time_limit">
                                    @foreach ([10, 15, 20, 30, 45, 60] as $t)<option value="{{ $t }}">{{ $t }}s</option>@endforeach
                                </select>
                            </label>
                            <label class="d-flex align-items-center gap-2">{{ __('Points') }}
                                <select class="form-select form-select-sm" style="width:auto" :name="`questions[${i}][points]`" x-model.number="q.points">
                                    <option value="500">{{ __('Half (500)') }}</option><option value="1000">{{ __('Standard (1000)') }}</option><option value="2000">{{ __('Double (2000)') }}</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-ff-outline" @click="add()"><i class="bi bi-plus-lg"></i> {{ __('Add another question') }}</button>
            </div>
        </div>
    </form>
</x-layouts.church-admin>
