<section class="ff-card">
    <h2 class="h5 mb-3">{{ __('Contact :church', ['church' => $church->name]) }}</h2>
    @if ($church->contactsVisibleTo(auth()->user()))
        <ul class="list-unstyled d-grid gap-3 mb-4">
            @if ($church->address || $church->city)
                <li class="d-flex gap-3"><i class="bi bi-geo-alt text-ff-primary fs-5"></i><span>{{ $church->address }}<br>{{ $church->location }}</span></li>
            @endif
            @if ($church->phone)
                <li class="d-flex gap-3"><i class="bi bi-telephone text-ff-primary fs-5"></i><a href="tel:{{ $church->phone }}">{{ $church->phone }}</a></li>
            @endif
            @if ($church->email)
                <li class="d-flex gap-3"><i class="bi bi-envelope text-ff-primary fs-5"></i><a href="mailto:{{ $church->email }}">{{ $church->email }}</a></li>
            @endif
            @if ($church->website)
                <li class="d-flex gap-3"><i class="bi bi-globe text-ff-primary fs-5"></i><a href="{{ $church->website }}" target="_blank" rel="noopener">{{ $church->website }}</a></li>
            @endif
        </ul>
    @else
        <p class="d-flex gap-3 mb-3"><i class="bi bi-geo-alt text-ff-primary fs-5"></i><span>{{ $church->location }}</span></p>
        <div class="mb-4">@include('partials.contact-locked')</div>
    @endif
    @php
        $points = $church->branches->filter(fn ($b) => $b->lat)->map(fn ($b) => ['lat' => $b->lat, 'lng' => $b->lng, 'label' => $b->name, 'sub' => $b->city])->values();
        if ($points->isEmpty() && $church->lat) {
            $points = collect([['lat' => $church->lat, 'lng' => $church->lng, 'label' => $church->name, 'sub' => $church->location]]);
        }
    @endphp
    @if ($points->isNotEmpty())
        <div class="rounded-4 overflow-hidden" style="height: 340px" data-ff-map="{{ json_encode($points) }}"></div>
        @if ($church->lat)
            <a class="small d-inline-block mt-2" href="https://www.google.com/maps/dir/?api=1&destination={{ $church->lat }},{{ $church->lng }}" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> {{ __('Get directions') }}</a>
        @endif
    @endif
</section>
