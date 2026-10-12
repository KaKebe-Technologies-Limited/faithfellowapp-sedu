@if ($ffLite ?? false)
    <div class="ff-lite-banner">
        <i class="bi bi-reception-1"></i> {{ __('Data-saver mode is on — images and video are reduced.') }}
        <a href="#" class="text-white text-decoration-underline ms-1" data-ff-lite-toggle>{{ __('Turn off') }}</a>
    </div>
@endif
