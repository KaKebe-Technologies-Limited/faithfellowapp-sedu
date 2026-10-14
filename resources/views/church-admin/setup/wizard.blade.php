<x-layouts.church-admin :church="$church" :title="__('Setup wizard')">
    <x-ff.page-header :title="__('Set up :church', ['church' => $church->name])" :subtitle="__('A few quick steps to get your church online. You can change everything later in Settings.')" />

    <div class="ff-steps mb-4" role="list">
        @foreach ($steps as $key => $label)
            @php($i = $loop->index)
            <a href="{{ route('church-admin.setup', [$church, 'step' => $key]) }}" role="listitem"
               @class(['ff-step text-decoration-none', 'is-active' => $key === $step, 'is-done' => $i < $stepIndex])>{{ $label }}</a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('church-admin.setup.update', $church) }}" enctype="multipart/form-data" class="ff-card p-4 p-md-5" style="max-width: 880px" novalidate>
        @csrf
        <input type="hidden" name="step" value="{{ $step }}">

        @if ($step === 'basics')
            <h2 class="h4">{{ __('The basics') }}</h2>
            <p class="text-ff-muted">{{ __('How your church appears in the directory and on your website.') }}</p>
            <x-ff.input name="name" :label="__('Church name')" :value="$church->name" required />
            <div class="row">
                <div class="col-md-6"><x-ff.select name="denomination" :label="__('Denomination')" :options="array_combine(config('faithfellows.denominations'), config('faithfellows.denominations'))" :value="$church->denomination" :placeholder="__('Select…')" /></div>
                <div class="col-md-6"><x-ff.input name="pastor_name" :label="__('Senior pastor')" :value="$church->pastor_name" /></div>
            </div>
            <x-ff.input name="tagline" :label="__('Tagline')" :value="$church->tagline" maxlength="160" />
            <x-ff.textarea name="description" :label="__('Short description')" :value="$church->description" rows="3" maxlength="1000" />
            <div class="row">
                <div class="col-md-4"><x-ff.input name="founded_year" type="number" :label="__('Year founded')" :value="$church->founded_year" min="1800" :max="date('Y')" /></div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Service languages') }}</label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach (config('faithfellows.languages') as $code => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="languages[]" value="{{ $code }}" id="w_lang_{{ $code }}" @checked(in_array($code, old('languages', $church->languages ?? ['en'])))>
                                <label class="form-check-label" for="w_lang_{{ $code }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif ($step === 'branding')
            <h2 class="h4">{{ __('Branding') }}</h2>
            <p class="text-ff-muted">{{ __('Your logo and cover photo appear on your church page, website and receipts.') }}</p>
            @include('church-admin.settings._branding-fields')
        @elseif ($step === 'location')
            <h2 class="h4">{{ __('Location') }}</h2>
            <p class="text-ff-muted">{{ __('Help people find you. Use “Use my location” while standing at the church.') }}</p>
            <div class="row">
                <div class="col-md-6"><x-ff.input name="city" :label="__('City / town')" :value="$church->city" required /></div>
                <div class="col-md-6"><x-ff.input name="district" :label="__('District')" :value="$church->district" /></div>
                <div class="col-12"><x-ff.input name="address" :label="__('Physical address / directions')" :value="$church->address" /></div>
            </div>
            <div class="row align-items-end" x-data="{ lat: '{{ old('lat', $church->lat) }}', lng: '{{ old('lng', $church->lng) }}', busy: false,
                locate() { if (!navigator.geolocation) return; this.busy = true; navigator.geolocation.getCurrentPosition(p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); this.busy = false; }, () => this.busy = false); } }">
                <div class="col-5"><label class="form-label" for="lat">{{ __('Latitude') }}</label><input class="form-control mb-3" id="lat" name="lat" x-model="lat" inputmode="decimal"></div>
                <div class="col-5"><label class="form-label" for="lng">{{ __('Longitude') }}</label><input class="form-control mb-3" id="lng" name="lng" x-model="lng" inputmode="decimal"></div>
                <div class="col-2"><button type="button" class="btn btn-ff-soft w-100 mb-3" @click="locate()" :disabled="busy" title="{{ __('Use my location') }}"><i class="bi bi-crosshair"></i></button></div>
            </div>
            <input type="hidden" name="country" value="{{ $church->country ?? 'UG' }}">
        @elseif ($step === 'contact')
            <h2 class="h4">{{ __('Contact & social') }}</h2>
            <div class="row">
                <div class="col-md-6"><x-ff.input name="phone" type="tel" :label="__('Phone')" :value="$church->phone" /></div>
                <div class="col-md-6"><x-ff.input name="email" type="email" :label="__('Email')" :value="$church->email" /></div>
                <div class="col-12"><x-ff.input name="website" type="url" :label="__('Existing website (optional)')" :value="$church->website" placeholder="https://" /></div>
            </div>
            <h3 class="h6 mt-2">{{ __('Social links') }}</h3>
            <div class="row">
                @foreach ($platforms as $platform)
                    @continue($platform === 'other')
                    <div class="col-md-6">
                        <x-ff.input :name="'social['.$platform.']'" type="url" :label="ucfirst($platform)" :value="$church->socialLinks->firstWhere('platform', $platform)?->url" placeholder="https://" />
                    </div>
                @endforeach
            </div>
        @elseif ($step === 'services')
            <h2 class="h4">{{ __('Service times') }}</h2>
            <p class="text-ff-muted">{{ __('Shown on your church page and used to find churches by service day.') }}</p>
            @include('church-admin.settings._service-times', ['services' => $church->serviceTimes, 'branches' => $church->branches])
        @endif

        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <a href="{{ route('church-admin.dashboard', $church) }}" class="btn btn-ff-ghost">{{ __('Finish later') }}</a>
            <button class="btn btn-ff-primary px-4">{{ __('Save & continue') }} <i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
</x-layouts.church-admin>
