@if ($sections['sermons']->isEmpty())
    <x-ff.empty-state icon="bi-mic" :title="__('No sermons yet')" :message="__(':church has not published sermons yet.', ['church' => $church->name])" />
@else
    <div class="row g-3">
        @foreach ($sections['sermons'] as $sermon)
            <div class="col-md-6">@include('member.sermons._card', ['sermon' => $sermon])</div>
        @endforeach
    </div>
@endif
@if ($sections['devotional'] ?? null)
    <a href="{{ route('devotionals.show', $sections['devotional']) }}" class="ff-verse-card d-block mt-4 text-decoration-none">
        <div class="small fw-semibold text-uppercase mb-2" style="opacity:.8">{{ __('Latest devotional') }}</div>
        <div class="fw-bold fs-5 text-white">{{ $sections['devotional']->title }}</div>
        <cite>{{ $sections['devotional']->scripture }}</cite>
    </a>
@endif
