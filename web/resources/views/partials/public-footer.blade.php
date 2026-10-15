@php($company = config('faithfellows.company'))
<footer class="ff-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <x-logo :size="36" white />
                <p class="mt-3 mb-4 ff-footer-line">{{ config('faithfellows.brand.tagline') }}.</p>
                <div class="d-flex gap-2">
                    <a href="https://www.facebook.com/" class="btn btn-icon btn-sm btn-outline-light border-0" aria-label="Facebook" rel="noopener" target="_blank"><i class="bi bi-facebook"></i></a>
                    <a href="https://x.com/" class="btn btn-icon btn-sm btn-outline-light border-0" aria-label="X" rel="noopener" target="_blank"><i class="bi bi-twitter-x"></i></a>
                    <a href="https://www.instagram.com/" class="btn btn-icon btn-sm btn-outline-light border-0" aria-label="Instagram" rel="noopener" target="_blank"><i class="bi bi-instagram"></i></a>
                    <a href="https://wa.me/256{{ ltrim($company['phone'], '0') }}" class="btn btn-icon btn-sm btn-outline-light border-0" aria-label="WhatsApp" rel="noopener" target="_blank"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            <div class="col-6 col-lg-2 offset-lg-1">
                <h4>{{ __('Company') }}</h4>
                <ul>
                    <li><a href="{{ route('about') }}">{{ __('About us') }}</a></li>
                    <li><a href="{{ route('pricing') }}">{{ __('Pricing') }}</a></li>
                    <li><a href="{{ route('blog.index') }}">{{ __('Blog') }}</a></li>
                    @if (Route::has('church.apply'))<li><a href="{{ route('church.apply') }}">{{ __('Register your church') }}</a></li>@endif
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h4>{{ __('Support') }}</h4>
                <ul>
                    <li><a href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
                    <li><a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="{{ route('terms') }}">{{ __('Terms of Service') }}</a></li>
                    <li><a href="{{ route('account.delete') }}">{{ __('Delete your account') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h4>{{ __('Get in touch') }}</h4>
                <ul>
                    <li class="d-flex gap-2"><i class="bi bi-telephone"></i><a href="tel:{{ $company['phone'] }}">{{ $company['phone'] }}</a></li>
                    <li class="d-flex gap-2"><i class="bi bi-envelope"></i><a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a></li>
                    <li class="d-flex gap-2"><i class="bi bi-geo-alt"></i><span>Lira City, Uganda</span></li>
                </ul>
            </div>
        </div>
        <div class="ff-footer-bottom d-flex flex-column flex-md-row justify-content-between gap-3">
            <span>&copy; {{ date('Y') }} {{ $company['name'] }}. {{ __('All rights reserved.') }}</span>
            <span class="d-flex gap-3 align-items-center flex-wrap">
                @foreach (config('faithfellows.languages') as $code => $label)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" @class(['fw-bold text-white' => app()->getLocale() === $code]) hreflang="{{ $code }}">{{ $label }}</a>
                @endforeach
                <a href="#" data-ff-lite-toggle><i class="bi bi-reception-2"></i> {{ ($ffLite ?? false) ? __('Full mode') : __('Data-saver mode') }}</a>
            </span>
        </div>
    </div>
</footer>
