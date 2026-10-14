{{-- Church photo slider. Expects $slides (HomeSlider::forUser) and $sliderId. --}}
@if ($slides->isNotEmpty())
    <div id="{{ $sliderId }}" class="carousel slide carousel-fade ff-home-slider mb-4" data-bs-ride="carousel" data-bs-interval="5500" data-bs-pause="hover" aria-roledescription="carousel" aria-label="{{ __('Church highlights') }}">
        <div class="carousel-indicators">
            @foreach ($slides as $i => $slide)
                <button type="button" data-bs-target="#{{ $sliderId }}" data-bs-slide-to="{{ $i }}" @class(['active' => $i === 0]) @if ($i === 0) aria-current="true" @endif aria-label="{{ __('Slide :n: :church', ['n' => $i + 1, 'church' => $slide['church']]) }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner">
            @foreach ($slides as $i => $slide)
                <div @class(['carousel-item', 'active' => $i === 0])>
                    <picture>
                        <source media="(max-width: 575.98px)" srcset="{{ $slide['image_sm'] }}">
                        <img src="{{ $slide['image'] }}" alt="" @if ($i > 0) loading="lazy" @endif>
                    </picture>
                    <div class="ff-slide-shade"></div>
                    <a href="{{ $slide['url'] }}" class="ff-slide-caption text-decoration-none">
                        @if ($slide['logo'])<img src="{{ $slide['logo'] }}" alt="" class="ff-slide-logo">@endif
                        <div class="min-w-0">
                            <div class="ff-slide-church">{{ $slide['church'] }}</div>
                            <h2>{{ $slide['title'] }}</h2>
                            @if ($slide['subtitle'])<p>{{ $slide['subtitle'] }}</p>@endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
        @if ($slides->count() > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#{{ $sliderId }}" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('Previous') }}</span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#{{ $sliderId }}" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('Next') }}</span></button>
        @endif
    </div>
@endif
