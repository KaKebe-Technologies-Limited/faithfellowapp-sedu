<section @class(['site-hero', 'has-cover' => $church->cover_url]) @if ($church->cover_url) style="--site-cover: url('{{ e($church->cover_url) }}')" @endif>
    <div class="container position-relative" style="z-index:1">
        <div style="max-width: 760px">
            @if ($church->denomination)<span class="ff-badge mb-3" style="background: rgba(255,255,255,.2); color:#fff">{{ $church->denomination }}</span>@endif
            <h1 class="ff-display mb-3">{{ $church->setting('hero_title') ?: $church->name }}</h1>
            <p class="fs-5 mb-4" style="opacity:.92">{{ $church->setting('hero_subtitle') ?: ($church->tagline ?: $church->description) }}</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ ff_platform_route('churches.show', $church) }}" class="btn btn-ff-white btn-lg px-4">{{ __('Join us on FaithFellows') }}</a>
                @if ($church->serviceTimes->isNotEmpty())
                    <a href="#services" class="btn btn-lg px-4 text-white border-white rounded-pill">{{ __('Service times') }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
