<x-layouts.super-admin :title="$podcast->name">
    <x-ff.page-header :title="$podcast->name" :subtitle="$podcast->host_name.' · '.$podcast->categoryLabel()" :breadcrumbs="[__('Podcasts') => route('super-admin.podcasts.index'), $podcast->name => null]">
        <x-ff.status :status="$podcast->status" />
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="ff-card">
                <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-3 w-100 mb-3" style="aspect-ratio:1;object-fit:cover">
                <p>{{ $podcast->summary }}</p>
                @if ($podcast->description)<p class="small text-ff-muted" style="white-space:pre-line">{{ $podcast->description }}</p>@endif
                <dl class="small mb-0">
                    <dt>{{ __('Podcaster') }}</dt><dd>{{ $podcast->owner?->name }} <span class="text-ff-muted">({{ $podcast->owner?->email }})</span></dd>
                    <dt>{{ __('Language') }}</dt><dd>{{ config('faithfellows.languages')[$podcast->language] ?? $podcast->language }}</dd>
                    @if ($podcast->website_url)<dt>{{ __('Website') }}</dt><dd><a href="{{ $podcast->website_url }}" target="_blank" rel="noopener">{{ $podcast->website_url }}</a></dd>@endif
                    @if ($podcast->review_note)<dt>{{ __('Note given') }}</dt><dd>{{ $podcast->review_note }}</dd>@endif
                </dl>
            </div>

            <div class="ff-card mt-3">
                <h2 class="h6">{{ __('Decision') }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if ($podcast->status !== 'approved')
                        <form method="POST" action="{{ route('super-admin.podcasts.approve', $podcast) }}">@csrf<button class="btn btn-ff-primary"><i class="bi bi-check-lg"></i> {{ __('Approve') }}</button></form>
                    @endif
                    @if ($podcast->status === 'approved')
                        <form method="POST" action="{{ route('super-admin.podcasts.hide', $podcast) }}" data-ff-confirm="{{ __('Hide this podcast from the app?') }}">@csrf<button class="btn btn-ff-soft"><i class="bi bi-eye-slash"></i> {{ __('Hide from the app') }}</button></form>
                    @endif
                </div>
                @if ($podcast->status !== 'rejected')
                    <form method="POST" action="{{ route('super-admin.podcasts.reject', $podcast) }}">@csrf
                        <label class="form-label small" for="reason">{{ __('Not approved? Tell the podcaster why') }}</label>
                        <textarea name="reason" id="reason" rows="2" class="form-control mb-2 @error('reason') is-invalid @enderror" placeholder="{{ __('For example: the podcast is not about faith, or the audio links do not open.') }}" required></textarea>
                        @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <button class="btn btn-ff-ghost text-danger btn-sm">{{ __('Not approved') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar"><strong>{{ __('Episodes') }}</strong> <span class="text-ff-muted small">{{ __('Listen to check the audio before you approve.') }}</span></div>
                <div class="table-responsive">
                    <table class="table ff-table align-middle">
                        <thead><tr><th>{{ __('Episode') }}</th><th>{{ __('Length') }}</th><th>{{ __('Status') }}</th><th>{{ __('Listen') }}</th></tr></thead>
                        <tbody>
                            @forelse ($episodes as $episode)
                                <tr>
                                    <td><div class="fw-semibold text-ff-black">{{ $episode->title }}</div><div class="small-xs text-ff-muted">{{ $episode->published_at?->format('j M Y') }}</div></td>
                                    <td class="small">{{ $episode->durationLabel() ?? '—' }}</td>
                                    <td><x-ff.status :status="$episode->status" /></td>
                                    <td><audio controls preload="none" src="{{ $episode->audio_url }}" style="height:34px;max-width:240px"></audio></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ff.empty-state icon="bi-collection-play" :title="__('No episodes yet')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.super-admin>
