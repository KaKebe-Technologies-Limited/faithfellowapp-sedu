<x-layouts.church-admin :church="$church" :title="__('Counseling')">
    <x-ff.page-header :title="__('Counseling')" :breadcrumbs="[__('Pastoral care') => route('church-admin.care.index', $church), __('Counseling') => null]" />
    <p class="ff-confidential mb-3"><i class="bi bi-shield-lock"></i> {{ __('Topics and session notes are encrypted and only shown to the assigned counselor and pastoral care staff.') }}</p>

    <nav class="ff-pills mb-4" aria-label="{{ __('Status') }}">
        @foreach (['' => __('Open'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled'), 'declined' => __('Declined')] as $value => $label)
            <a href="{{ route('church-admin.counseling.index', [$church, 'status' => $value ?: null]) }}" @class(['ff-pill', 'active' => ($status ?? '') === $value])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="d-grid gap-3" style="max-width: 980px">
        @forelse ($appointments as $a)
            <article class="ff-card" x-data="{ open: {{ $a->status === 'requested' ? 'true' : 'false' }} }">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <x-ff.avatar :user="$a->member" />
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-ff-black">{{ $a->member?->name }}</div>
                        <div class="small text-ff-muted">
                            {{ ['in_person' => __('In person'), 'phone' => __('Phone'), 'video' => __('Video')][$a->mode] }}
                            @if ($a->scheduled_at) · <i class="bi bi-calendar-check"></i> {{ $a->scheduled_at->format('D j M, g:i A') }}
                            @elseif ($a->preferred_at) · {{ __('prefers :when', ['when' => $a->preferred_at->format('D j M, g:i A')]) }}@endif
                            @if ($a->counselor) · {{ $a->counselor->name }}@endif
                            · {{ __('requested :ago', ['ago' => $a->created_at->diffForHumans()]) }}
                        </div>
                    </div>
                    <x-ff.status :status="$a->status" />
                    <button class="btn btn-sm btn-ff-ghost" @click="open = !open" :aria-expanded="open">{{ __('Manage') }} <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i></button>
                </div>

                <div x-show="open" x-cloak class="mt-3 pt-3 border-top">
                    @can('viewNotes', $a)
                        <div class="small p-3 rounded-3 bg-ff-mist mb-3"><strong class="text-ff-black">{{ __('Topic:') }}</strong> {{ $a->topic }}</div>
                    @endcan
                    <form method="POST" action="{{ route('church-admin.counseling.update', [$church, $a]) }}">@csrf @method('PUT')
                        <div class="row g-2">
                            <div class="col-md-4"><x-ff.select name="counselor_id" :label="__('Counselor')" :options="$counselors" :value="$a->counselor_id" :placeholder="__('— Assign —')" /></div>
                            <div class="col-md-4"><x-ff.input type="datetime-local" name="scheduled_at" :label="__('Date & time')" :value="($a->scheduled_at ?? $a->preferred_at)?->format('Y-m-d\TH:i')" /></div>
                            <div class="col-md-2"><x-ff.input type="number" name="duration_minutes" :label="__('Minutes')" :value="$a->duration_minutes" min="15" max="240" /></div>
                            <div class="col-md-2"><x-ff.select name="status" :label="__('Status')" :options="['requested' => __('Requested'), 'confirmed' => __('Confirmed'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled'), 'declined' => __('Declined')]" :value="$a->status" /></div>
                        </div>
                        @can('viewNotes', $a)
                            <x-ff.textarea name="counselor_notes" :label="__('Session notes (encrypted)')" :value="$a->counselor_notes" rows="3" maxlength="5000" />
                        @endcan
                        <button class="btn btn-ff-primary btn-sm">{{ __('Save') }}</button>
                        <span class="small text-ff-muted ms-2">{{ __('Confirming with a time notifies the member.') }}</span>
                    </form>
                </div>
            </article>
        @empty
            <x-ff.empty-state icon="bi-person-heart" :title="__('No counseling appointments')" />
        @endforelse
    </div>
    <div class="mt-4">{{ $appointments->links() }}</div>
</x-layouts.church-admin>
