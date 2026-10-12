@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div {{ $attributes->class('ff-page-header') }}>
    <div class="min-w-0">
        @if ($breadcrumbs)
            <nav class="ff-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
                @foreach ($breadcrumbs as $label => $url)
                    @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                    @if (! $loop->last) <i class="bi bi-chevron-right mx-1" style="font-size:.6rem"></i> @endif
                @endforeach
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
    @endif
</div>
