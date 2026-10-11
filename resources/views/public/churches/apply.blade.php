<x-layouts.public :title="__('Register your church')" :description="__('Bring your church onto FaithFellows — website, sermons, live streams, giving and member care.')">
    <section class="ff-page-hero">
        <div class="container" style="max-width: 980px">
            <span class="ff-eyebrow">{{ __('Church onboarding') }}</span>
            <h1 class="ff-display mt-3 mb-3" style="font-size: clamp(2rem, 4.5vw, 3rem)">{{ __('Register your church') }}</h1>
            <p class="ff-lead mb-0">{{ __('Tell us about your church. Our team reviews every application, usually within 48 hours. After approval you get a church website, admin dashboard and giving tools.') }}</p>
        </div>
    </section>

    <section class="ff-section pt-4">
        <div class="container" style="max-width: 980px">
            @guest
                <div class="ff-card p-4 p-md-5 text-center">
                    <div class="ff-feature-icon mx-auto"><i class="bi bi-person-plus"></i></div>
                    <h2 class="h4">{{ __('First, create your personal account') }}</h2>
                    <p class="text-ff-muted mx-auto" style="max-width:520px">{{ __('The person who registers the church becomes its first church admin. You can add more staff later.') }}</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="{{ route('register') }}" class="btn btn-ff-primary px-4">{{ __('Create account') }}</a>
                        <a href="{{ route('login') }}" class="btn btn-ff-outline px-4">{{ __('I already have an account') }}</a>
                    </div>
                </div>
            @else
                <div class="row g-4">
                    <div class="col-lg-8">
                        <form method="POST" action="{{ route('church.apply.store') }}" class="ff-card p-4 p-md-5" novalidate>
                            @csrf
                            <div class="ff-form-section">
                                <h3>{{ __('About your church') }}</h3>
                                <x-ff.input name="name" :label="__('Church name')" required :placeholder="__('e.g. Grace Community Church Lira')" />
                                <div class="row">
                                    <div class="col-md-6">
                                        <x-ff.select name="denomination" :label="__('Denomination')" :options="array_combine(config('faithfellows.denominations'), config('faithfellows.denominations'))" :placeholder="__('Select…')" />
                                    </div>
                                    <div class="col-md-6"><x-ff.input name="pastor_name" :label="__('Senior pastor')" /></div>
                                </div>
                                <x-ff.textarea name="description" :label="__('Short description')" rows="3" maxlength="1000" :help="__('Shown in the church directory.')" />
                                <label class="form-label">{{ __('Service languages') }}</label>
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    @foreach (config('faithfellows.languages') as $code => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="languages[]" value="{{ $code }}" id="lang_{{ $code }}" @checked(in_array($code, old('languages', ['en'])))>
                                            <label class="form-check-label" for="lang_{{ $code }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="ff-form-section">
                                <h3>{{ __('Location & contact') }}</h3>
                                <div class="row">
                                    <div class="col-md-6"><x-ff.input name="city" :label="__('City / town')" required /></div>
                                    <div class="col-md-6"><x-ff.input name="district" :label="__('District')" /></div>
                                    <div class="col-12"><x-ff.input name="address" :label="__('Physical address')" /></div>
                                    <div class="col-md-6"><x-ff.input name="phone" type="tel" :label="__('Church phone')" required /></div>
                                    <div class="col-md-6"><x-ff.input name="email" type="email" :label="__('Church email')" /></div>
                                </div>
                                <input type="hidden" name="country" value="UG">
                            </div>
                            <div class="ff-form-section">
                                <x-ff.textarea name="application_notes" :label="__('Anything else we should know?')" rows="3" :help="__('Number of members, branches, current tools, preferred onboarding date…')" />
                                <div class="form-check mb-4">
                                    <input class="form-check-input @error('agree') is-invalid @enderror" type="checkbox" name="agree" id="agree" value="1" @checked(old('agree'))>
                                    <label class="form-check-label small" for="agree">{{ __('I am authorised to register this church and accept the FaithFellows Terms of Service (UGX 60,000/month, free hosting for the first year).') }}</label>
                                    @error('agree')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if (! auth()->user()->hasVerifiedEmail())
                                    <div class="alert alert-warning small">{{ __('Please verify your email address before submitting.') }}</div>
                                @endif
                                <button class="btn btn-ff-primary btn-lg px-5">{{ __('Submit application') }}</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-4">
                        <div class="ff-card ff-card-mist">
                            <h2 class="h6">{{ __('What happens next') }}</h2>
                            <ol class="small ps-3 mb-0 d-grid gap-2">
                                <li>{{ __('You finish your profile in the setup wizard (logo, location, service times).') }}</li>
                                <li>{{ __('Our team reviews and approves your church.') }}</li>
                                <li>{{ __('Your website goes live at yourchurch.:domain.', ['domain' => config('faithfellows.root_domain')]) }}</li>
                                <li>{{ __('You invite staff and start publishing.') }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            @endguest
        </div>
    </section>
</x-layouts.public>
