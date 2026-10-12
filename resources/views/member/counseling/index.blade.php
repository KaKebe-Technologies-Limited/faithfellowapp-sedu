<x-layouts.member :title="__('Counseling')">
    <x-ff.page-header :title="__('Counseling')" :subtitle="__('Book a private conversation with your pastoral team.')" />

    <div class="row g-4">
        <div class="col-lg-5">
            @if ($churches->isEmpty())
                <x-ff.empty-state icon="bi-bookmark-heart" :title="__('Follow a church first')" :message="__('Counseling is offered by the churches you follow.')">
                    <a href="{{ route('churches.index') }}" class="btn btn-ff-primary mt-2">{{ __('Find your church') }}</a>
                </x-ff.empty-state>
            @else
                <form method="POST" action="{{ route('counseling.store') }}" class="ff-card">
                    @csrf
                    <h2 class="h5">{{ __('Request an appointment') }}</h2>
                    <p class="ff-confidential mb-3"><i class="bi bi-shield-lock"></i> {{ __('Confidential. Only the pastoral care team can read what you write here, and it is encrypted.') }}</p>
                    <x-ff.select name="church_id" :label="__('Church')" :options="$churches" required />
                    <x-ff.textarea name="topic" :label="__('What would you like to talk about?')" rows="4" maxlength="2000" required :help="__('A sentence or two is enough.')" />
                    <x-ff.select name="mode" :label="__('How would you like to meet?')" :options="['in_person' => __('In person'), 'phone' => __('Phone call'), 'video' => __('Video call')]" required />
                    <x-ff.input type="datetime-local" name="preferred_at" :label="__('Preferred time (optional)')" min="{{ now()->format('Y-m-d\TH:i') }}" />
                    <button class="btn btn-ff-primary w-100"><i class="bi bi-send"></i> {{ __('Send request') }}</button>
                </form>
            @endif
        </div>

        <div class="col-lg-7">
            <h2 class="h6 text-uppercase text-ff-muted mb-3">{{ __('My appointments') }}</h2>
            @forelse ($appointments as $a)
                <div class="ff-card ff-card-sm d-flex flex-wrap gap-3 align-items-center mb-2">
                    <x-ff.church-avatar :church="$a->church" size="sm" />
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-ff-black">{{ $a->church->name }}</div>
                        <div class="small text-ff-muted">
                            @if ($a->scheduled_at)
                                <i class="bi bi-calendar-check"></i> {{ $a->scheduled_at->format('D j M, g:i A') }} · {{ $a->duration_minutes }} {{ __('min') }}
                            @elseif ($a->preferred_at)
                                {{ __('Requested for :when', ['when' => $a->preferred_at->format('D j M, g:i A')]) }}
                            @else
                                {{ __('Requested :ago', ['ago' => $a->created_at->diffForHumans()]) }}
                            @endif
                            · {{ ['in_person' => __('In person'), 'phone' => __('Phone'), 'video' => __('Video')][$a->mode] }}
                            @if ($a->counselor) · {{ __('with :name', ['name' => $a->counselor->name]) }}@endif
                        </div>
                    </div>
                    <x-ff.status :status="$a->status" />
                    @can('cancel', $a)
                        <form method="POST" action="{{ route('counseling.cancel', $a) }}" data-ff-confirm="{{ __('Cancel this appointment?') }}">@csrf
                            <button class="btn btn-sm btn-ff-ghost">{{ __('Cancel') }}</button>
                        </form>
                    @endcan
                </div>
            @empty
                <x-ff.empty-state icon="bi-person-heart" :title="__('No appointments yet')" :message="__('Whatever you are carrying, you do not have to carry it alone.')" />
            @endforelse
        </div>
    </div>
</x-layouts.member>
