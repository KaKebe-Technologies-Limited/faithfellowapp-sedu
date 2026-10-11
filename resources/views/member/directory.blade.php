<x-layouts.member :title="__('Member directory')">
    <x-ff.page-header :title="__('Member directory')" :subtitle="__('Only members who opted in are listed. You can opt in or out under Profile → Preferences.')">
        <a href="{{ route('profile.edit', ['tab' => 'preferences']) }}" class="btn btn-ff-ghost btn-sm">
            <i class="bi {{ $user->directory_opt_in ? 'bi-eye' : 'bi-eye-slash' }}"></i> {{ $user->directory_opt_in ? __('You are listed') : __('You are hidden') }}
        </a>
    </x-ff.page-header>

    @if ($churches->isEmpty())
        <x-ff.empty-state icon="bi-person-lines-fill" :title="__('No churches yet')" :message="__('Subscribe to a church to see its member directory.')">
            <a href="{{ route('churches.index') }}" class="btn btn-ff-primary">{{ __('Find a church') }}</a>
        </x-ff.empty-state>
    @else
        <form method="GET" class="d-flex flex-wrap gap-2 mb-4">
            <select name="church" class="form-select" style="max-width: 320px" aria-label="{{ __('Church') }}" onchange="this.form.submit()">
                @foreach ($churches as $c)<option value="{{ $c->slug }}" @selected($church?->id === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width: 280px" placeholder="{{ __('Search by name…') }}" aria-label="{{ __('Search') }}">
            <button class="btn btn-ff-primary">{{ __('Search') }}</button>
        </form>

        @if ($members->isEmpty())
            <x-ff.empty-state icon="bi-people" :title="__('No one is listed yet')" :message="__('Members appear here when they opt in to the directory.')" />
        @else
            <div class="row g-3">
                @foreach ($members as $person)
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="ff-card text-center h-100">
                            <x-ff.avatar :user="$person" size="xl" class="mx-auto mb-2" />
                            <div class="fw-semibold text-ff-black">{{ $person->name }}</div>
                            @if ($person->city)<div class="small text-ff-muted"><i class="bi bi-geo-alt"></i> {{ $person->city }}</div>@endif
                            @if ($person->bio)<p class="small text-ff-muted mt-2 mb-0">{{ Str::limit($person->bio, 90) }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 d-flex justify-content-center">{{ $members->links() }}</div>
        @endif
    @endif
</x-layouts.member>
