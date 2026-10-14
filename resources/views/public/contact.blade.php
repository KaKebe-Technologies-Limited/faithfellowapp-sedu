<x-layouts.public :title="__('Contact')" :description="__('Contact the FaithFellows team at Kakebe Technologies, Lira City.')">
    @php($company = config('faithfellows.company'))
    <section class="ff-page-hero">
        <div class="container">
            <span class="ff-eyebrow">{{ __('Contact us') }}</span>
            <h1 class="ff-display mt-3 mb-3">{{ __('We would love to hear from you') }}</h1>
            <p class="ff-lead mb-0" style="max-width: 640px">{{ __('Questions about onboarding your church, pricing or support? Send us a message and our team will respond within one working day.') }}</p>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="ff-card p-4 p-md-5">
                        <form method="POST" action="{{ route('contact.store') }}" novalidate>
                            @csrf
                            <div class="row">
                                <div class="col-md-6"><x-ff.input name="name" :label="__('Your name')" required autocomplete="name" /></div>
                                <div class="col-md-6"><x-ff.input name="email" type="email" :label="__('Email')" required autocomplete="email" /></div>
                                <div class="col-md-6"><x-ff.input name="phone" type="tel" :label="__('Phone')" autocomplete="tel" /></div>
                                <div class="col-md-6"><x-ff.input name="subject" :label="__('Subject')" /></div>
                            </div>
                            <x-ff.textarea name="message" :label="__('Message')" rows="6" required />
                            {{-- Honeypot field — hidden from humans --}}
                            <div class="d-none" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <button class="btn btn-ff-primary btn-lg px-5">{{ __('Send message') }} <i class="bi bi-send ms-1"></i></button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5 d-grid gap-4 align-content-start">
                    <div class="ff-card">
                        <h2 class="h5 mb-3">{{ $company['name'] }}</h2>
                        <ul class="list-unstyled d-grid gap-3 mb-0">
                            <li class="d-flex gap-3"><i class="bi bi-geo-alt text-ff-primary fs-5"></i><span>{{ $company['address'] }}<br>{{ $company['po_box'] }}</span></li>
                            <li class="d-flex gap-3"><i class="bi bi-telephone text-ff-primary fs-5"></i><a href="tel:{{ $company['phone'] }}">{{ $company['phone'] }}</a></li>
                            <li class="d-flex gap-3"><i class="bi bi-envelope text-ff-primary fs-5"></i><a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a></li>
                        </ul>

                    </div>
                    <div class="ff-card ff-card-flush">
                        <iframe title="{{ __('Map to Kakebe Technologies') }}" src="https://www.openstreetmap.org/export/embed.html?bbox={{ $company['lng'] - 0.01 }}%2C{{ $company['lat'] - 0.007 }}%2C{{ $company['lng'] + 0.01 }}%2C{{ $company['lat'] + 0.007 }}&amp;layer=mapnik&amp;marker={{ $company['lat'] }}%2C{{ $company['lng'] }}"
                            style="width:100%;height:280px;border:0" loading="lazy"></iframe>
                        <div class="p-3 small"><a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($company['map_query']) }}" target="_blank" rel="noopener"><i class="bi bi-map"></i> {{ __('Open in Google Maps') }}</a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
