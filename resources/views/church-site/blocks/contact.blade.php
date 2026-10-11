<section class="ff-section site-section" id="contact">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <span class="ff-eyebrow site-accent">{{ __('Visit us') }}</span>
                <h2 class="mt-2 mb-4">{{ __('Get in touch') }}</h2>
                <ul class="list-unstyled d-grid gap-3">
                    @if ($church->address || $church->city)<li class="d-flex gap-3"><i class="bi bi-geo-alt fs-5 site-accent"></i><span>{{ $church->address }}<br>{{ $church->location }}</span></li>@endif
                    @if ($church->phone)<li class="d-flex gap-3"><i class="bi bi-telephone fs-5 site-accent"></i><a href="tel:{{ $church->phone }}">{{ $church->phone }}</a></li>@endif
                    @if ($church->email)<li class="d-flex gap-3"><i class="bi bi-envelope fs-5 site-accent"></i><a href="mailto:{{ $church->email }}">{{ $church->email }}</a></li>@endif
                </ul>
                @if ($church->socialLinks->isNotEmpty())
                    <div class="d-flex gap-2 mt-3">
                        @foreach ($church->socialLinks as $link)
                            <a href="{{ $link->url }}" class="btn btn-icon btn-ff-soft" target="_blank" rel="noopener" aria-label="{{ ucfirst($link->platform) }}"><i class="bi {{ $link->icon }}"></i></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-lg-7">
                @if ($church->lat && $church->lng)
                    <div class="rounded-4 overflow-hidden h-100" style="min-height: 320px" data-ff-map="{{ json_encode([['lat' => $church->lat, 'lng' => $church->lng, 'label' => $church->name, 'sub' => $church->address]]) }}"></div>
                @endif
            </div>
        </div>
    </div>
</section>
