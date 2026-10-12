@php
    $prayers = ($sections['prayer'] ?? collect())->where('visibility', 'public')->take(3);
    $testimony = ($sections['testimonies'] ?? collect())->first();
@endphp
<section class="ff-section site-section ff-section-alt" id="prayer">
    <div class="container">
        <div class="text-center mb-5">
            <span class="ff-eyebrow site-accent">{{ __('Prayer') }}</span>
            <h2 class="mt-2">{{ __('How can we pray for you?') }}</h2>
            <p class="text-ff-muted mx-auto" style="max-width: 560px">{{ __('Our church family prays over every request. You can share openly, anonymously, or with the pastors only.') }}</p>
            <a href="{{ ff_platform_route('prayer.create', ['church' => $church->id]) }}" class="btn btn-ff-primary mt-2" style="background: var(--site-accent); border-color: var(--site-accent)">{{ __('Send a prayer request') }}</a>
        </div>

        @if ($prayers->isNotEmpty() || $testimony)
            <div class="row g-4 justify-content-center">
                @foreach ($prayers as $prayer)
                    <div class="col-md-4">
                        <div class="ff-prayer-card">
                            <strong class="text-ff-black">{{ $prayer->author_name }}</strong>
                            <blockquote>{{ Str::limit($prayer->body, 160) }}</blockquote>
                            <div class="small text-ff-muted mt-auto"><span aria-hidden="true">🙏</span> {{ trans_choice(':count person prayed|:count people prayed', $prayer->prayed_count) }}</div>
                        </div>
                    </div>
                @endforeach
                @if ($testimony)
                    <div class="col-lg-8">
                        <figure class="ff-card text-center mb-0">
                            <i class="bi bi-quote fs-1 site-accent"></i>
                            <blockquote class="fs-5 text-ff-black">{{ Str::limit($testimony->body, 320) }}</blockquote>
                            <figcaption class="small text-ff-muted">— {{ $testimony->author_name }}, “{{ $testimony->title }}”</figcaption>
                        </figure>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
