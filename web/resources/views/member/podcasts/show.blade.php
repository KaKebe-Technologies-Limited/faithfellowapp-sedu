<x-layouts.member :title="$podcast->name">
    <x-ff.page-header :title="$podcast->name" :subtitle="$podcast->host_name.' · '.$podcast->categoryLabel()" :breadcrumbs="[__('My podcasts') => route('podcasts.index'), $podcast->name => null]">
        <a href="{{ route('podcasts.edit', $podcast) }}" class="btn btn-ff-soft"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        <a href="{{ route('podcasts.episodes.create', $podcast) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Add an episode') }}</a>
    </x-ff.page-header>

    @switch ($podcast->status)
        @case ('pending')
            <div class="alert alert-warning"><i class="bi bi-hourglass-split"></i> {{ __('Your podcast is waiting for review. You can add episodes now; listeners see it in the app once it is approved.') }}</div>
            @break
        @case ('approved')
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> {{ __('Your podcast is live in the FaithFellows app. Listeners can find it, follow it and play your episodes.') }}</div>
            @break
        @case ('rejected')
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <strong>{{ __('Not approved yet.') }}</strong> {{ $podcast->review_note }} {{ __('Change what is needed and save: it goes back for review.') }}</div>
            @break
        @default
            <div class="alert alert-secondary"><i class="bi bi-eye-slash"></i> {{ __('Your podcast is hidden from the app.') }} {{ $podcast->review_note }}</div>
    @endswitch

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="ff-card">
                <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-3 w-100 mb-3" style="aspect-ratio:1;object-fit:cover">
                <p class="mb-2">{{ $podcast->summary }}</p>
                <div class="small text-ff-muted d-flex gap-3">
                    <span><i class="bi bi-collection-play"></i> {{ $podcast->episodes_count }}</span>
                    <span><i class="bi bi-heart"></i> {{ $podcast->followers_count }} {{ __('followers') }}</span>
                </div>
            </div>
            @if ($podcast->feed_url)
                <div class="ff-card mt-3 small">
                    <h2 class="h6"><i class="bi bi-rss text-ff-primary"></i> {{ __('Synced from your RSS feed') }}</h2>
                    <p class="mb-2 text-ff-muted text-break">{{ $podcast->feed_url }}</p>
                    @if ($podcast->feed_error)
                        <div class="alert alert-warning py-2 small">{{ $podcast->feed_error }}</div>
                    @endif
                    <p class="mb-2">{{ $podcast->feed_synced_at ? __('Last checked :when.', ['when' => $podcast->feed_synced_at->diffForHumans()]) : __('Not checked yet.') }} {{ __('New episodes from your host are added automatically; change episodes on your host, not here.') }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST" action="{{ route('podcasts.sync', $podcast) }}">@csrf<button class="btn btn-sm btn-ff-soft"><i class="bi bi-arrow-repeat"></i> {{ __('Check now') }}</button></form>
                        <form method="POST" action="{{ route('podcasts.feed.disconnect', $podcast) }}" data-ff-confirm="{{ __('Stop syncing with the feed? Your episodes stay.') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ff-ghost text-danger">{{ __('Disconnect') }}</button></form>
                    </div>
                </div>
            @endif
            @if ($podcast->isListed())
                <div class="ff-card mt-3 small">
                    <h2 class="h6"><i class="bi bi-globe text-ff-primary"></i> {{ __('On the website') }}</h2>
                    <p class="mb-2">{{ __('Your podcast has its own page:') }} <a href="{{ route('podcasts.listen.show', $podcast->slug) }}" target="_blank" rel="noopener">{{ route('podcasts.listen.show', $podcast->slug) }}</a></p>
                    <label class="form-label small mb-1" for="embed-code">{{ __('Player to put on another website') }}</label>
                    <textarea id="embed-code" class="form-control form-control-sm font-monospace" rows="3" readonly onclick="this.select()">&lt;iframe src="{{ route('podcasts.embed', $podcast->slug) }}" width="100%" height="420" style="border:0;border-radius:12px" title="{{ $podcast->name }}" loading="lazy"&gt;&lt;/iframe&gt;</textarea>
                </div>
            @endif
            <div class="ff-card mt-3 small">
                <h2 class="h6">{{ __('Where to keep your audio') }}</h2>
                @if ($hosts)
                    <p class="mb-1">{{ __('Upload each episode to one of these sites, then paste its direct link when you add the episode:') }}</p>
                    <ul class="mb-0">@foreach ($hosts as $host)<li>{{ $host }}</li>@endforeach</ul>
                @else
                    <p class="mb-0">{{ __('Upload each episode to an audio host, then paste its direct https link (it usually ends in .mp3) when you add the episode. The list of recommended sites will appear here.') }}</p>
                @endif
            </div>
        </div>

        <div class="col-lg-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar"><strong>{{ __('Episodes') }}</strong></div>
                <div class="table-responsive">
                    <table class="table ff-table align-middle">
                        <thead><tr><th>{{ __('Episode') }}</th><th>{{ __('Length') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($episodes as $episode)
                                <tr>
                                    <td><div class="fw-semibold text-ff-black">{{ $episode->title }}</div><div class="small-xs text-ff-muted text-truncate" style="max-width:340px">{{ $episode->audio_url }}</div></td>
                                    <td class="small">{{ $episode->durationLabel() ?? '—' }}</td>
                                    <td class="small text-nowrap">{{ $episode->published_at?->format('j M Y') }}</td>
                                    <td><x-ff.status :status="$episode->status === 'published' && $episode->published_at?->isFuture() ? 'scheduled' : $episode->status" /></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('podcasts.episodes.edit', [$podcast, $episode]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('podcasts.episodes.destroy', [$podcast, $episode]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this episode?') }}">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ff.empty-state icon="bi-collection-play" :title="__('No episodes yet')" :message="__('Add your first episode: a title, a few words about it, and the link to its audio.')" class="border-0">
                                    <a href="{{ route('podcasts.episodes.create', $podcast) }}" class="btn btn-ff-primary mt-2">{{ __('Add an episode') }}</a>
                                </x-ff.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="POST" action="{{ route('podcasts.destroy', $podcast) }}" class="mt-3" data-ff-confirm="{{ __('Delete this podcast and all its episodes?') }}">@csrf @method('DELETE')
                <button class="btn btn-sm btn-ff-ghost text-danger"><i class="bi bi-trash"></i> {{ __('Delete this podcast') }}</button>
            </form>
        </div>
    </div>
</x-layouts.member>
