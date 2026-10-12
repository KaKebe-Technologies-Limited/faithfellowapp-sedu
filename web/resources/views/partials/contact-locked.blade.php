{{-- Shown instead of a church's contact details to people who don't follow it. Expects $church. --}}
<div class="ff-contact-locked">
    <i class="bi bi-shield-lock"></i>
    <div class="flex-grow-1">
        <strong class="text-ff-black d-block">{{ __('Contact details are for members') }}</strong>
        <span class="small">{{ __('Subscribe to :church to see their phone number, email and social pages.', ['church' => $church->name]) }}</span>
    </div>
    @auth
        @include('public.churches._subscribe-button', ['church' => $church, 'sections' => $sections ?? []])
    @else
        <a href="{{ route('churches.join', $church) }}" class="btn btn-ff-primary btn-sm text-nowrap">{{ __('Sign in & subscribe') }}</a>
    @endauth
</div>
