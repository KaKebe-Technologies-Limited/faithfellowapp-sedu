<x-layouts.member :title="__('Testimonies')">
    <x-ff.page-header :title="__('Testimonies')" :subtitle="__('Stories of God’s faithfulness from your church family.')">
        <a href="{{ route('testimonies.create') }}" class="btn btn-ff-primary"><i class="bi bi-pencil"></i> {{ __('Share your testimony') }}</a>
    </x-ff.page-header>

    @if ($mine->isNotEmpty())
        <div class="ff-card ff-card-sm mb-4">
            <h2 class="h6 mb-2">{{ __('Your submissions') }}</h2>
            @foreach ($mine as $t)
                <div class="d-flex justify-content-between align-items-center py-1 small">
                    <span class="text-truncate">{{ $t->title }} <span class="text-ff-muted">· {{ $t->church->name }}</span></span>
                    <x-ff.status :status="$t->status">{{ $t->status === 'pending' ? __('Awaiting review') : __('Not published') }}</x-ff.status>
                </div>
            @endforeach
        </div>
    @endif

    @if ($testimonies->isEmpty())
        <x-ff.empty-state icon="bi-stars" :title="__('No testimonies yet')" :message="__('When God answers prayer, tell the story — it will encourage someone today.')" />
    @else
        <div class="row g-3" style="max-width: 1100px">
            @foreach ($testimonies as $testimony)
                <div class="col-lg-6">@include('member.testimonies._card')</div>
            @endforeach
        </div>
        <div class="mt-4">{{ $testimonies->links() }}</div>
    @endif
</x-layouts.member>
