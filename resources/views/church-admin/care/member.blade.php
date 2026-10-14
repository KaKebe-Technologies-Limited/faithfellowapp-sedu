<x-layouts.church-admin :church="$church" :title="$member->name">
    <x-ff.page-header :title="$member->name" :breadcrumbs="[__('Pastoral care') => route('church-admin.care.index', $church), $member->name => null]">
        @if ($member->phone)<a href="tel:{{ $member->phone }}" class="btn btn-ff-soft"><i class="bi bi-telephone"></i> {{ __('Call') }}</a>@endif
        <button class="btn btn-ff-soft" data-bs-toggle="modal" data-bs-target="#visit-modal"><i class="bi bi-house-heart"></i> {{ __('Schedule visit') }}</button>
        <button class="btn btn-ff-soft" data-bs-toggle="modal" data-bs-target="#welfare-modal"><i class="bi bi-basket"></i> {{ __('Record welfare') }}</button>
    </x-ff.page-header>

    <div class="row g-4">
        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card text-center">
                <x-ff.avatar :user="$member" size="xl" class="mb-2" />
                <div class="fw-bold text-ff-black">{{ $member->name }}</div>
                <div class="small text-ff-muted">{{ Str::headline($subscription->stage) }} · {{ __('since :d', ['d' => $subscription->subscribed_at?->format('M Y')]) }}</div>
                <ul class="list-unstyled small text-start mt-3 mb-0 d-grid gap-1">
                    @if ($member->phone)<li><i class="bi bi-telephone text-ff-primary"></i> {{ $member->phone }}</li>@endif
                    <li><i class="bi bi-envelope text-ff-primary"></i> {{ $member->email }}</li>
                    @if ($member->date_of_birth)<li><i class="bi bi-cake2 text-ff-primary"></i> {{ $member->date_of_birth->format('j F') }}</li>@endif
                    @if ($member->wedding_anniversary)<li><i class="bi bi-heart text-ff-primary"></i> {{ __('Anniversary :d', ['d' => $member->wedding_anniversary->format('j F')]) }}</li>@endif
                    <li><i class="bi bi-calendar-check text-ff-primary"></i> {{ trans_choice(':count service attended in 12 weeks|:count services attended in 12 weeks', $attendance) }}</li>
                    @foreach ($groups as $g)<li><i class="bi bi-people text-ff-primary"></i> <a href="{{ route('groups.show', $g) }}">{{ $g->name }}</a></li>@endforeach
                </ul>
            </div>

            <x-ff.card :title="__('Discipleship pathway')">
                <div class="ff-milestones">
                    @foreach (\App\Models\DiscipleshipRecord::MILESTONES as $key => $label)
                        @php($done = $milestones[$key] ?? null)
                        <div @class(['ff-milestone', 'is-done' => $done])>
                            <i @class(['bi', 'bi-check-circle-fill text-success' => $done, 'bi-circle text-ff-muted' => ! $done])></i>
                            <span class="flex-grow-1 small">{{ __($label) }}@if ($done)<span class="d-block small-xs text-ff-muted">{{ $done->completed_on->format('j M Y') }}@if ($done->mentor) · {{ $done->mentor->name }}@endif</span>@endif</span>
                            @if ($done)
                                <form method="POST" action="{{ route('church-admin.care.milestones.destroy', [$church, $done]) }}">@csrf @method('DELETE')
                                    <button class="btn btn-link p-0 text-ff-muted small" aria-label="{{ __('Undo') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('church-admin.care.milestones.store', $church) }}">@csrf
                                    <input type="hidden" name="user_id" value="{{ $member->id }}"><input type="hidden" name="milestone" value="{{ $key }}">
                                    <input type="hidden" name="completed_on" value="{{ today()->toDateString() }}"><input type="hidden" name="mentor_id" value="{{ auth()->id() }}">
                                    <button class="btn btn-sm btn-ff-ghost py-0" title="{{ __('Mark complete today') }}">{{ __('Done') }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ff.card>
        </aside>

        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Pastoral notes')">
                <form method="POST" action="{{ route('church-admin.care.notes.store', $church) }}" class="mb-3">@csrf
                    <input type="hidden" name="user_id" value="{{ $member->id }}">
                    <x-ff.textarea name="body" :label="__('Add a note')" rows="3" maxlength="5000" required />
                    <div class="d-flex align-items-center gap-3">
                        <x-ff.checkbox name="is_private" :label="__('Private — only I can read this')" :checked="true" switch />
                        <button class="btn btn-ff-primary btn-sm ms-auto">{{ __('Save note') }}</button>
                    </div>
                </form>
                @forelse ($notes as $note)
                    <div class="py-2 border-top">
                        <div class="small-xs text-ff-muted d-flex gap-2 align-items-center">
                            <strong class="text-ff-black">{{ $note->author?->name ?? __('Former staff') }}</strong> · {{ $note->created_at->format('j M Y, H:i') }}
                            @if ($note->is_private)<span class="ff-badge ff-badge-dark"><i class="bi bi-lock-fill"></i> {{ __('Private') }}</span>@endif
                            @if ($note->author_id === auth()->id())
                                <form method="POST" action="{{ route('church-admin.care.notes.destroy', [$church, $note]) }}" class="ms-auto" data-ff-confirm="{{ __('Delete this note?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-link p-0 text-ff-muted" aria-label="{{ __('Delete note') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                        <div class="small" style="white-space: pre-line">{{ $note->body }}</div>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No notes yet.') }}</p>
                @endforelse
            </x-ff.card>

            <x-ff.card :title="__('Home visits')">
                @forelse ($visits as $v)
                    <div class="py-2 @if (! $loop->first) border-top @endif">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="small fw-semibold text-ff-black">{{ $v->scheduled_at->format('D j M Y, g:i A') }}</span>
                            <x-ff.status :status="$v->status" />
                            <span class="small text-ff-muted">{{ $v->purpose }}@if ($v->visitor) · {{ $v->visitor->name }}@endif</span>
                        </div>
                        @if ($v->notes)<div class="small mt-1" style="white-space: pre-line">{{ $v->notes }}</div>@endif
                        @if ($v->status === 'scheduled')
                            <form method="POST" action="{{ route('church-admin.care.visits.update', [$church, $v]) }}" class="d-flex gap-2 mt-2">@csrf @method('PUT')
                                <input name="notes" class="form-control form-control-sm" maxlength="5000" placeholder="{{ __('How did it go? (encrypted)') }}" aria-label="{{ __('Visit notes') }}">
                                <button name="status" value="completed" class="btn btn-sm btn-ff-primary text-nowrap">{{ __('Completed') }}</button>
                                <button name="status" value="cancelled" class="btn btn-sm btn-ff-ghost">{{ __('Cancel') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No visits yet.') }}</p>
                @endforelse
            </x-ff.card>

            <x-ff.card :title="__('Welfare support')">
                @forelse ($welfare as $w)
                    <div class="d-flex justify-content-between py-1 small @if (! $loop->first) border-top @endif">
                        <span><span class="ff-tag">{{ __(\App\Models\WelfareRecord::TYPES[$w->type] ?? $w->type) }}</span> {{ $w->notes }}</span>
                        <span class="text-nowrap">{{ $w->amount > 0 ? ff_money($w->amount) : '' }} <span class="text-ff-muted">{{ $w->given_on->format('j M Y') }}</span></span>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No welfare support recorded.') }}</p>
                @endforelse
            </x-ff.card>
        </div>
    </div>

    @include('church-admin.care._modals', ['memberId' => $member->id])
</x-layouts.church-admin>
