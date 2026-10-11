@if ($church->about || $church->description)
    <section class="ff-section site-section" id="about">
        <div class="container" style="max-width: 900px">
            <span class="ff-eyebrow site-accent">{{ __('About us') }}</span>
            <h2 class="mt-2 mb-4">{{ __('Welcome to :church', ['church' => $church->name]) }}</h2>
            @if ($church->about)
                <div class="ff-prose">{!! ff_safe_html($church->about) !!}</div>
            @else
                <p class="ff-lead">{{ $church->description }}</p>
            @endif
            @if ($church->pastor_name)
                <p class="fw-semibold mt-4 mb-0">— {{ $church->pastor_name }}, {{ __('Senior Pastor') }}</p>
            @endif
        </div>
    </section>
@endif
