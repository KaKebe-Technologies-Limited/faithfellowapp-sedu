{{-- Shared church profile fields. Expects optional $church. --}}
@php($church ??= new \App\Models\Church)
<div class="ff-form-section">
    <h3>{{ __('Church details') }}</h3>
    <div class="row">
        <div class="col-md-8"><x-ff.input name="name" :label="__('Church name')" :value="$church->name" required /></div>
        <div class="col-md-4"><x-ff.input name="founded_year" type="number" :label="__('Founded')" :value="$church->founded_year" min="1800" :max="date('Y')" /></div>
        <div class="col-md-6"><x-ff.select name="denomination" :label="__('Denomination')" :options="array_combine(config('faithfellows.denominations'), config('faithfellows.denominations'))" :value="$church->denomination" :placeholder="__('Select…')" /></div>
        <div class="col-md-6"><x-ff.input name="pastor_name" :label="__('Senior pastor')" :value="$church->pastor_name" /></div>
    </div>
    <x-ff.input name="tagline" :label="__('Tagline')" :value="$church->tagline" maxlength="160" :placeholder="__('e.g. A family of believers in the heart of Lira')" />
    <x-ff.textarea name="description" :label="__('Short description')" :value="$church->description" rows="2" maxlength="1000" :help="__('Shown in the directory and search results.')" />
    @if ($showAbout ?? false)
        <x-ff.textarea name="about" :label="__('About (full story)')" :value="$church->about" rows="6" editor />
    @endif
    <label class="form-label">{{ __('Service languages') }}</label>
    <div class="d-flex flex-wrap gap-3 mb-2">
        @foreach (config('faithfellows.languages') as $code => $label)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="languages[]" value="{{ $code }}" id="lang_{{ $code }}" @checked(in_array($code, old('languages', $church->languages ?? ['en'])))>
                <label class="form-check-label" for="lang_{{ $code }}">{{ $label }}</label>
            </div>
        @endforeach
    </div>
</div>
<div class="ff-form-section">
    <h3>{{ __('Location') }}</h3>
    <div class="row">
        <div class="col-md-6"><x-ff.input name="city" :label="__('City / town')" :value="$church->city" required /></div>
        <div class="col-md-6"><x-ff.input name="district" :label="__('District')" :value="$church->district" /></div>
        <div class="col-12"><x-ff.input name="address" :label="__('Address')" :value="$church->address" /></div>
        <div class="col-6"><x-ff.input name="lat" type="number" step="any" :label="__('Latitude')" :value="$church->lat" /></div>
        <div class="col-6"><x-ff.input name="lng" type="number" step="any" :label="__('Longitude')" :value="$church->lng" /></div>
    </div>
    <input type="hidden" name="country" value="{{ $church->country ?? 'UG' }}">
</div>
<div class="ff-form-section">
    <h3>{{ __('Contact') }}</h3>
    <div class="row">
        <div class="col-md-6"><x-ff.input name="phone" type="tel" :label="__('Phone')" :value="$church->phone" /></div>
        <div class="col-md-6"><x-ff.input name="email" type="email" :label="__('Email')" :value="$church->email" /></div>
        <div class="col-12"><x-ff.input name="website" type="url" :label="__('Existing website')" :value="$church->website" placeholder="https://" /></div>
    </div>
</div>
