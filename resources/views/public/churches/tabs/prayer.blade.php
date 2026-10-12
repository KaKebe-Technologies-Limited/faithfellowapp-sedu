<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="mb-0 text-ff-muted">{{ __('Pray with :church. Tap “I prayed” to encourage the person who asked.', ['church' => $church->name]) }}</p>
    @auth
        @if ($sections['isMember'])
            <a href="{{ route('prayer.create', ['church' => $church->id]) }}" class="btn btn-sm btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Share a request') }}</a>
        @endif
    @else
        <a href="{{ route('login') }}" class="btn btn-sm btn-ff-soft">{{ __('Sign in to share a request') }}</a>
    @endauth
</div>

@if ($sections['prayer']->isEmpty())
    <x-ff.empty-state icon="bi-chat-heart" :title="__('No open prayer requests')" />
@else
    <div class="row g-3 mb-4">
        @foreach ($sections['prayer'] as $prayer)
            <div class="col-md-6">@include('member.prayer._card', ['prayed' => in_array($prayer->id, $sections['prayedIds'], true), 'limit' => 220])</div>
        @endforeach
    </div>
@endif

@if ($sections['testimonies']->isNotEmpty())
    <h2 class="h5 mt-4 mb-3"><i class="bi bi-stars text-ff-primary"></i> {{ __('Testimonies') }}</h2>
    <div class="d-grid gap-3">
        @foreach ($sections['testimonies'] as $testimony)
            @include('member.testimonies._card', ['engagement' => auth()->check()])
        @endforeach
    </div>
@endif
