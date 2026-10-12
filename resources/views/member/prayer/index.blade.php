<x-layouts.member :title="__('Prayer wall')">
    <x-ff.page-header :title="__('Prayer wall')" :subtitle="__('Carry one another’s burdens. Share a request, and let people know you prayed.')">
        <a href="{{ route('prayer.create') }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Share a request') }}</a>
    </x-ff.page-header>

    @foreach ($chainAsks as $ask)
        <div class="ff-card ff-card-sm ff-chain-ask d-flex flex-wrap align-items-center gap-3 mb-3">
            <i class="bi bi-link-45deg fs-3 text-ff-primary"></i>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-ff-black">{{ __('You have been asked to pray — you are the next link in a prayer chain.') }}</div>
                <div class="small text-ff-muted text-truncate">{{ $ask->prayerRequest?->church?->name }} · {{ Str::limit($ask->prayerRequest?->body, 90) }}</div>
            </div>
            @if ($ask->prayerRequest)<a href="{{ route('prayer.show', $ask->prayerRequest) }}" class="btn btn-sm btn-ff-primary">{{ __('Pray now') }}</a>@endif
        </div>
    @endforeach

    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
        <nav class="ff-pills" aria-label="{{ __('Filter') }}">
            <a href="{{ route('prayer.index', ['church' => request('church')]) }}" @class(['ff-pill', 'active' => $tab === 'wall'])>{{ __('Open requests') }}</a>
            <a href="{{ route('prayer.index', ['tab' => 'answered', 'church' => request('church')]) }}" @class(['ff-pill', 'active' => $tab === 'answered'])><i class="bi bi-stars"></i> {{ __('Answered') }}</a>
            <a href="{{ route('prayer.index', ['tab' => 'mine']) }}" @class(['ff-pill', 'active' => $tab === 'mine'])>{{ __('My requests') }}</a>
        </nav>
        @if ($churches->count() > 1 && $tab !== 'mine')
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select name="church" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Church') }}">
                    <option value="">{{ __('All my churches') }}</option>
                    @foreach ($churches as $c)<option value="{{ $c->slug }}" @selected(request('church') === $c->slug)>{{ $c->name }}</option>@endforeach
                </select>
            </form>
        @endif
    </div>

    @if ($prayers->isEmpty())
        <x-ff.empty-state icon="bi-chat-heart" :title="$tab === 'mine' ? __('You have not shared a request yet') : __('No prayer requests here yet')"
            :message="__('Be the first to share — your church family is ready to pray with you.')">
            <a href="{{ route('prayer.create') }}" class="btn btn-ff-primary mt-2">{{ __('Share a request') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-3">
            @foreach ($prayers as $prayer)
                <div class="col-md-6 col-xl-4">
                    @include('member.prayer._card', ['prayed' => in_array($prayer->id, $myPrayed, true)])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $prayers->links() }}</div>
    @endif
</x-layouts.member>
