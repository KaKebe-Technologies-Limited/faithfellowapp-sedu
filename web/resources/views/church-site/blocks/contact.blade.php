@php($showContacts = $church->contactsVisibleTo(auth()->user()))
<section class="ff-section site-section" id="contact">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <span class="ff-eyebrow site-accent">{{ __('Visit us') }}</span>
                <h2 class="mt-2 mb-4">{{ __('Get in touch') }}</h2>
                <ul class="list-unstyled d-grid gap-3">
                    <li class="d-flex gap-3"><i class="bi bi-geo-alt fs-5 site-accent"></i><span>@if ($showContacts && $church->address){{ $church->address }}<br>@endif{{ $church->location }}</span></li>
                    @if ($showContacts && $church->phone)<li class="d-flex gap-3"><i class="bi bi-telephone fs-5 site-accent"></i><a href="tel:{{ $church->phone }}">{{ $church->phone }}</a></li>@endif
                    @if ($showContacts && $church->email)<li class="d-flex gap-3"><i class="bi bi-envelope fs-5 site-accent"></i><a href="mailto:{{ $church->email }}">{{ $church->email }}</a></li>@endif
                </ul>
                @if ($showContacts && $church->socialLinks->isNotEmpty())
                    <div class="d-flex gap-2 mt-3">
                        @foreach ($church->socialLinks as $link)
                            <a href="{{ $link->url }}" class="btn btn-icon btn-ff-soft" target="_blank" rel="noopener" aria-label="{{ ucfirst($link->platform) }}"><i class="bi {{ $link->icon }}"></i></a>
                        @endforeach
                    </div>
                @endif
                @unless ($showContacts)
                    <div class="ff-card ff-card-mist mt-3">
                        <p class="mb-2"><i class="bi bi-shield-lock site-accent"></i> {{ __('Our phone number, email and social pages are shared with members.') }}</p>
                        <a href="{{ ff_platform_route('churches.join', $church) }}" class="btn btn-ff-primary" style="background: var(--site-accent); border-color: var(--site-accent)">{{ __('Join us on FaithFellows') }}</a>
                    </div>
                @endunless
            </div>
            <div class="col-lg-7">
                @if ($church->lat && $church->lng)
                    <div class="rounded-4 overflow-hidden h-100" style="min-height: 320px" data-ff-map="{{ json_encode([['lat' => $church->lat, 'lng' => $church->lng, 'label' => $church->name, 'sub' => $church->location]]) }}"></div>
                @endif
            </div>
        </div>
    </div>
</section>
