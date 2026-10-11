<x-layouts.member :title="__('Sermons')">
    <x-ff.page-header :title="__('Sermons')" :subtitle="__('Messages from all your churches — stream or save for offline listening.')">
        <a href="{{ route('offline') }}" class="btn btn-ff-ghost"><i class="bi bi-download"></i> {{ __('Saved offline') }}</a>
    </x-ff.page-header>

    <form method="GET" class="ff-card ff-card-sm mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Title, preacher, scripture or words spoken…') }}" aria-label="{{ __('Search sermons') }}"></div>
            <div class="col-6 col-lg-2">
                <select name="church" class="form-select" aria-label="{{ __('Church') }}">
                    <option value="">{{ __('All churches') }}</option>
                    @foreach ($churches as $slug => $name)<option value="{{ $slug }}" @selected(request('church') === $slug)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="preacher" class="form-select" aria-label="{{ __('Preacher') }}">
                    <option value="">{{ __('Any preacher') }}</option>
                    @foreach ($preachers as $p)<option @selected(request('preacher') === $p)>{{ $p }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select name="language" class="form-select" aria-label="{{ __('Language') }}">
                    <option value="">{{ __('Any language') }}</option>
                    @foreach (config('faithfellows.languages') as $code => $label)<option value="{{ $code }}" @selected(request('language') === $code)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2"><button class="btn btn-ff-primary w-100">{{ __('Filter') }}</button></div>
        </div>
    </form>

    @if ($sermons->isEmpty())
        <x-ff.empty-state icon="bi-mic" :title="__('No sermons yet')" :message="__('When your churches publish sermons, they will appear here.')" />
    @else
        <div class="row g-4">
            @foreach ($sermons as $sermon)
                <div class="col-sm-6 col-lg-4 col-xxl-3">@include('member.sermons._card')</div>
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-center">{{ $sermons->links() }}</div>
    @endif
</x-layouts.member>
