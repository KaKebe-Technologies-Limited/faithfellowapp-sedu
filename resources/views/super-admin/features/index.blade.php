<x-layouts.super-admin :title="__('App features')">
    <x-ff.page-header :title="__('App features')" :subtitle="__('Podcasts, the hymn book and the gospel radio belong to the whole platform. Choose which churches’ members see them in the app.')" />

    <div class="alert alert-light border small mb-4">
        <i class="bi bi-info-circle"></i>
        {{ __('Everything is on for every church by default. People who have not joined a church yet always see all three. Someone who follows several churches sees a feature when at least one of them has it on. Switching a church off hides it from its members’ apps; their data is not touched.') }}
    </div>

    @if (session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach (\App\Support\AppFeatures::FEATURES as $key => $feature)
            <div class="col-md-4">
                <div class="ff-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-ff-muted small">{{ __($feature['label']) }}</div>
                            <div class="fs-4 fw-semibold">{{ $totals[$key] }} <span class="fs-6 text-ff-muted">/ {{ $total }} {{ __('churches') }}</span></div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('super-admin.features.all') }}" class="d-flex gap-2 mt-3" onsubmit="return confirm('{{ __('Change this for every church?') }}')">
                        @csrf
                        <input type="hidden" name="feature" value="{{ $key }}">
                        <button name="value" value="1" class="btn btn-sm btn-outline-success">{{ __('All on') }}</button>
                        <button name="value" value="0" class="btn btn-sm btn-outline-secondary">{{ __('All off') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('super-admin.features.update') }}">
        @csrf @method('PUT')
        <div class="ff-table-wrap">
            <div class="ff-table-toolbar">
                <strong>{{ __('Church by church') }}</strong>
                <button class="btn btn-ff-primary btn-sm ms-auto"><i class="bi bi-check2"></i> {{ __('Save this page') }}</button>
            </div>
            <div class="table-responsive">
                <table class="table ff-table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Church') }}</th>
                            @foreach (\App\Support\AppFeatures::FEATURES as $feature)
                                <th class="text-center">{{ __($feature['label']) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($churches as $church)
                            <tr>
                                <td>
                                    <input type="hidden" name="ids[]" value="{{ $church->id }}">
                                    <div class="d-flex align-items-center gap-2">
                                        <x-ff.church-avatar :church="$church" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('super-admin.churches.show', $church) }}" class="fw-semibold text-ff-black">{{ $church->name }}</a>
                                            <div class="small-xs text-ff-muted">{{ $church->location }}</div>
                                        </div>
                                    </div>
                                </td>
                                @foreach (\App\Support\AppFeatures::FEATURES as $key => $feature)
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" name="on[{{ $key }}][]" value="{{ $church->id }}" @checked($church->{$feature['column']}) aria-label="{{ __($feature['label']) }}: {{ $church->name }}">
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-ff-muted py-4">{{ __('No churches found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($churches->hasPages())<div class="ff-table-footer">{{ $churches->links() }}</div>@endif
        </div>
    </form>

    <form method="GET" class="mt-3" role="search">
        <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" style="max-width:320px" placeholder="{{ __('Search churches…') }}" aria-label="{{ __('Search churches') }}">
    </form>
</x-layouts.super-admin>
