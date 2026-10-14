@php($next = $church->serviceTimes->sortBy(fn ($s) => ($s->day_of_week - now()->dayOfWeek + 7) % 7)->first())
<article class="ff-card ff-card-flush ff-card-hover h-100 d-flex flex-column">
    <div class="position-relative">
        @if ($church->cover_url)
            <img src="{{ $church->cover_url }}" alt="" class="ff-cover" loading="lazy" style="aspect-ratio: 16/7">
        @else
            <div class="ff-cover-placeholder" style="aspect-ratio: 16/7; background: linear-gradient(135deg, {{ $church->brand_color }}, #F27EA8)"><i class="bi bi-buildings"></i></div>
        @endif
        <x-ff.church-avatar :church="$church" size="lg" class="position-absolute ff-avatar-ring" style="left: 20px; bottom: -24px" />
    </div>
    <div class="p-4 pt-5 d-flex flex-column flex-grow-1">
        <h3 class="h6 mb-1"><a href="{{ route('churches.show', $church) }}" class="text-ff-black stretched-link">{{ $church->name }}</a></h3>
        <div class="small text-ff-muted mb-2"><i class="bi bi-geo-alt"></i> {{ $church->location }} @if ($church->denomination) · {{ $church->denomination }} @endif</div>
        @if ($church->description)
            <p class="small text-ff-muted mb-3">{{ Str::limit($church->description, 110) }}</p>
        @endif
        <div class="mt-auto d-flex align-items-center gap-2 flex-wrap">
            @if ($next)
                <span class="ff-badge"><i class="bi bi-clock"></i> {{ $next->day_name }} {{ \Illuminate\Support\Carbon::parse($next->start_time)->format('g:i A') }}</span>
            @endif
            @if (in_array($church->id, $subscribedIds ?? [], true))
                <span class="ff-badge ff-badge-success"><i class="bi bi-check2"></i> {{ __('Subscribed') }}</span>
            @endif
        </div>
    </div>
</article>
