<x-layouts.member :title="__('My podcasts')">
    <x-ff.page-header :title="__('My podcasts')" :subtitle="__('Make a faith-based podcast and reach listeners in the FaithFellows app. You keep your audio on a host site and add the link to each episode.')">
        <a href="{{ route('podcasts.create') }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New podcast') }}</a>
    </x-ff.page-header>

    @if ($podcasts->isEmpty())
        <x-ff.empty-state icon="bi-mic" :title="__('You have no podcast yet')" :message="__('Give it a name and a short summary, then add your episodes. We review every podcast so the app stays faith-based.')">
            <a href="{{ route('podcasts.create') }}" class="btn btn-ff-primary mt-2"><i class="bi bi-plus-lg"></i> {{ __('Start your podcast') }}</a>
        </x-ff.empty-state>
    @else
        <div class="row g-3">
            @foreach ($podcasts as $podcast)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('podcasts.show', $podcast) }}" class="ff-card d-flex gap-3 text-decoration-none h-100">
                        <img src="{{ $podcast->coverUrl() }}" alt="" class="rounded-3 flex-shrink-0" style="width:84px;height:84px;object-fit:cover">
                        <div class="min-w-0">
                            <div class="fw-semibold text-ff-black text-truncate">{{ $podcast->name }}</div>
                            <div class="small text-ff-muted text-truncate">{{ $podcast->host_name }} · {{ $podcast->categoryLabel() }}</div>
                            <div class="mt-2 d-flex gap-2 align-items-center small">
                                <x-ff.status :status="$podcast->status" />
                                <span class="text-ff-muted">{{ trans_choice(':n episode|:n episodes', $podcast->episodes_total, ['n' => $podcast->episodes_total]) }}</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.member>
