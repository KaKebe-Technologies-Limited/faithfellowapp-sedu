<article class="ff-card ff-feed-item ff-card-mist">
    <div class="ff-feed-meta">
        <x-ff.church-avatar :church="$devotional->church" size="sm" />
        <div class="min-w-0">
            <span class="fw-semibold text-ff-black">{{ $devotional->church->name }}</span>
            <div class="small-xs text-ff-muted">{{ $devotional->publish_date->isToday() ? __('Today') : $devotional->publish_date->format('l, j M') }}</div>
        </div>
        <span class="ff-feed-type ff-tag"><i class="bi bi-book"></i> {{ __('Devotional') }}</span>
    </div>
    <a href="{{ route('devotionals.show', $devotional) }}" class="ff-card-link">
        <div class="fw-bold text-ff-black fs-5">{{ $devotional->title }}</div>
        @if ($devotional->scripture_text)
            <blockquote class="mb-1 mt-2 fst-italic">“{{ Str::limit($devotional->scripture_text, 200) }}”</blockquote>
        @endif
        @if ($devotional->scripture)<div class="small fw-semibold text-ff-primary">{{ $devotional->scripture }}</div>@endif
    </a>
</article>
