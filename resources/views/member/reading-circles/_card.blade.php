{{-- Reading partners card for a plan. Expects $plan and $circles (the member's circles for it). --}}
<x-ff.card :title="__('Reading partners')" :subtitle="__('Read with up to 3 friends from church. You get reminded together and see each other’s summaries.')">
    @foreach ($circles as $circle)
        <a href="{{ route('reading-circles.show', $circle) }}" class="d-flex align-items-center gap-2 p-2 rounded-3 bg-ff-mist mb-2 text-decoration-none">
            <span class="ff-avatar-stack">@foreach ($circle->members as $m)<x-ff.avatar :user="$m" size="xs" />@endforeach</span>
            <span class="min-w-0 flex-grow-1">
                <span class="d-block fw-semibold text-ff-black small text-truncate">{{ $circle->name }}</span>
                <span class="small-xs text-ff-muted">{{ $circle->scheduleLabel() }}</span>
            </span>
            <i class="bi bi-chevron-right text-ff-muted"></i>
        </a>
    @endforeach

    <div x-data="{ mode: @js($circles->isEmpty() ? 'start' : null) }">
        <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm flex-fill" :class="mode === 'start' ? 'btn-ff-primary' : 'btn-ff-soft'" @click="mode = mode === 'start' ? null : 'start'"><i class="bi bi-plus-lg"></i> {{ __('Start a circle') }}</button>
            <button type="button" class="btn btn-sm flex-fill" :class="mode === 'join' ? 'btn-ff-primary' : 'btn-ff-soft'" @click="mode = mode === 'join' ? null : 'join'"><i class="bi bi-key"></i> {{ __('Join with code') }}</button>
        </div>

        <form method="POST" action="{{ route('reading-circles.store', $plan) }}" class="mt-3" x-show="mode === 'start'" x-cloak>@csrf
            <x-ff.input name="name" :label="__('Circle name')" maxlength="80" required :value="__('Morning Readers')" />
            <x-ff.input type="time" name="reading_time" :label="__('We read at')" value="06:00" required />
            <div class="form-label">{{ __('On these days') }}</div>
            <div class="d-flex flex-wrap gap-1 mb-3">
                @foreach (\App\Models\ReadingCircle::DAYS as $i => $d)
                    <input type="checkbox" class="btn-check" name="reading_days[]" value="{{ $i }}" id="rd-{{ $plan->id }}-{{ $i }}" checked autocomplete="off">
                    <label class="btn btn-sm btn-outline-secondary rounded-pill px-2" for="rd-{{ $plan->id }}-{{ $i }}">{{ __($d) }}</label>
                @endforeach
            </div>
            <button class="btn btn-ff-primary w-100">{{ __('Create & get a code') }}</button>
        </form>

        <form method="POST" action="{{ route('reading-circles.join') }}" class="mt-3" x-show="mode === 'join'" x-cloak>@csrf
            <label class="form-label" for="circle-code-{{ $plan->id }}">{{ __('Pairing code') }}</label>
            <div class="d-flex gap-2">
                <input name="code" id="circle-code-{{ $plan->id }}" class="form-control text-uppercase fw-bold" style="letter-spacing:.25em" maxlength="6" minlength="6" required placeholder="ABC123" autocomplete="off">
                <button class="btn btn-ff-primary">{{ __('Join') }}</button>
            </div>
            @error('code')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
        </form>
    </div>
</x-ff.card>
